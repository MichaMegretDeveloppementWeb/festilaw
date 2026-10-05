<?php

use App\Data\Payment\CheckoutSessionData;
use App\Enums\Payment\PaymentEventOutcome;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Exceptions\Payment\PaymentException;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', [
        'secret_key' => 'sk_test_x',
        'webhook_secret' => 'whsec_x',
    ]);
    // La migration de la base de test (premier test du lancement) peut deja avoir construit la passerelle
    // avec la cle du .env : on la reconstruit avec celle du test.
    app()->forgetInstance(PaymentGatewayRegistry::class);
    app()->forgetInstance(StripePaymentGateway::class);
});

function stripePendingPayment(): Payment
{
    $submission = Submission::factory()->starter()->create([
        'resume_token' => 'tok',
        'locale' => 'en',
        'email' => 'buyer@example.com',
    ]);

    return $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'status' => PaymentStatus::Pending,
    ]);
}

/** Builds a Stripe webhook Request with a valid (or overridden) Stripe-Signature header. */
function stripeWebhookRequest(array $payload, string $secret = 'whsec_x', ?int $timestamp = null, ?string $signatureOverride = null): Request
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp ??= now()->timestamp;
    $signature = $signatureOverride ?? hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return Request::create('/webhooks/payment/stripe', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $body);
}

it('creates a Stripe Checkout session and returns the hosted url', function () {
    Http::fake([
        '*/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_1',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_1',
        ]),
    ]);

    $session = app(StripePaymentGateway::class)->createCheckout(stripePendingPayment());

    expect($session)->toBeInstanceOf(CheckoutSessionData::class)
        ->and($session->providerReference)->toBe('cs_test_1')
        ->and($session->redirectUrl)->toBe('https://checkout.stripe.com/c/pay/cs_test_1');

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v1/checkout/sessions')
        && $req->hasHeader('Authorization', 'Bearer sk_test_x')
        && str_contains($req->body(), 'mode=payment')
        && str_contains($req->body(), '33300'));
});

it('sends a stable Idempotency-Key on the checkout POST (a retry cannot create a second session)', function () {
    Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);

    $payment = stripePendingPayment();
    app(StripePaymentGateway::class)->createCheckout($payment);

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v1/checkout/sessions')
        && $req->hasHeader('Idempotency-Key', 'checkout-'.$payment->id));
});

it('notes under the Stripe pay button that the Scale consultation is booked right after paying, and only for the audit', function () {
    Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/x'])]);

    $subscription = stripePendingPayment();
    $audit = Submission::factory()->scale()->create()->payments()->create([
        'type' => PaymentType::ScaleAudit,
        'amount_cents' => 7500,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'status' => PaymentStatus::Pending,
    ]);

    app()->setLocale('fr');
    app(StripePaymentGateway::class)->createCheckout($audit);
    app(StripePaymentGateway::class)->createCheckout($subscription);

    // Le mot suit la langue du visiteur.
    Http::assertSent(fn ($req) => $req->hasHeader('Idempotency-Key', 'checkout-'.$audit->id)
        && str_contains(urldecode($req->body()), 'custom_text[submit][message]=Après le paiement, vous revenez sur votre espace Scale pour réserver votre consultation.'));
    Http::assertSent(fn ($req) => $req->hasHeader('Idempotency-Key', 'checkout-'.$subscription->id)
        && ! str_contains(urldecode($req->body()), 'custom_text'));
});

it('names a pack upgrade line as an upgrade to the Pro Pack (the dossier is still Creator)', function () {
    Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_up', 'url' => 'https://checkout.stripe.com/x'])]);
    $upgrade = stripePendingPayment();
    $upgrade->update(['type' => PaymentType::PackUpgrade, 'amount_cents' => 21675, 'service_year' => 2026]);

    app(StripePaymentGateway::class)->createCheckout($upgrade->fresh());

    Http::assertSent(fn ($req) => str_contains(urldecode($req->body()), 'Festilaw Pro Pack upgrade 2026'));
});

it('fully refunds a payment through its checkout session, idempotently', function () {
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_paid', 'payment_intent' => 'pi_123']),
        '*/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded']),
    ]);
    $payment = stripePendingPayment();
    $payment->update(['provider_reference' => 'cs_paid', 'status' => PaymentStatus::Succeeded]);

    app(StripePaymentGateway::class)->refund($payment->fresh());

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v1/refunds')
        && $req->hasHeader('Idempotency-Key', 'refund-'.$payment->id)
        && str_contains($req->body(), 'payment_intent=pi_123'));
});

it('reports a refused refund as a payment error', function () {
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_paid', 'payment_intent' => 'pi_123']),
        '*/v1/refunds' => Http::response(['error' => ['message' => 'charge_already_refunded']], 400),
    ]);
    $payment = stripePendingPayment();
    $payment->update(['provider_reference' => 'cs_paid', 'status' => PaymentStatus::Succeeded]);

    app(StripePaymentGateway::class)->refund($payment->fresh());
})->throws(PaymentException::class);

it('hands Stripe signed return URLs keyed by the payment, never the dossier token (which may rotate meanwhile)', function () {
    Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_scale', 'url' => 'https://checkout.stripe.com/x'])]);

    $submission = Submission::factory()->scale()->create(['resume_token' => 'scaletok', 'resume_expires_at' => now()->addDays(30)]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::ScaleAudit,
        'amount_cents' => 7500,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'status' => PaymentStatus::Pending,
    ]);

    app(StripePaymentGateway::class)->createCheckout($payment);

    Http::assertSent(function ($req) use ($payment) {
        $body = urldecode($req->body());

        return str_contains($body, 'success_url=')
            && str_contains($body, 'get-started/payment/'.$payment->id.'/return?expires=')
            && str_contains($body, 'status=success')
            && str_contains($body, 'status=cancelled')
            && str_contains($body, 'signature=')
            && ! str_contains($body, 'scaletok');
    });
});

it('confirms a paid checkout session via polling', function () {
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'payment_status' => 'paid'])]);

    $payment = stripePendingPayment();
    $payment->update(['provider_reference' => 'cs_1']);

    $event = app(StripePaymentGateway::class)->checkStatus($payment);

    expect($event->isPaid())->toBeTrue()
        ->and($event->providerReference)->toBe('cs_1');
});

it('reports an unpaid checkout session as still pending', function () {
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'payment_status' => 'unpaid'])]);

    $payment = stripePendingPayment();
    $payment->update(['provider_reference' => 'cs_1']);

    expect(app(StripePaymentGateway::class)->checkStatus($payment)->isPaid())->toBeFalse();
});

it('returns the in-flight checkout url for an open session (resume reuse)', function () {
    Http::fake(['*/v1/checkout/sessions/*' => Http::response([
        'id' => 'cs_1',
        'status' => 'open',
        'url' => 'https://checkout.stripe.com/c/pay/cs_1',
    ])]);

    $payment = stripePendingPayment();
    $payment->update(['provider_reference' => 'cs_1']);

    expect(app(StripePaymentGateway::class)->currentCheckoutUrl($payment))
        ->toBe('https://checkout.stripe.com/c/pay/cs_1');
});

it('returns null from currentCheckoutUrl when the session is no longer open', function () {
    Http::fake(['*/v1/checkout/sessions/*' => Http::response([
        'id' => 'cs_1',
        'status' => 'complete',
        'url' => 'https://checkout.stripe.com/c/pay/cs_1',
    ])]);

    $payment = stripePendingPayment();
    $payment->update(['provider_reference' => 'cs_1']);

    expect(app(StripePaymentGateway::class)->currentCheckoutUrl($payment))->toBeNull();
});

it('reports an async_payment_failed event as failed and not paid', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'checkout.session.async_payment_failed',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'unpaid']],
    ]));

    expect($event->isPaid())->toBeFalse()
        ->and($event->isFailed())->toBeTrue();
});

it('carries our payment id (client_reference_id) for reconciliation', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid', 'client_reference_id' => '42']],
    ]));

    expect($event->isPaid())->toBeTrue()
        ->and($event->clientReference)->toBe('42');
});

it('parses a valid Stripe webhook and reports the payment as paid', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid']],
    ]));

    expect($event->isPaid())->toBeTrue()
        ->and($event->providerReference)->toBe('cs_1');
});

it('does not confirm a completed session whose payment_status is not paid', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'unpaid']],
    ]));

    expect($event->isPaid())->toBeFalse();
});

it('rejects a Stripe webhook with an invalid signature', function () {
    $request = stripeWebhookRequest(
        ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid']]],
        signatureOverride: 'deadbeef',
    );

    expect(fn () => app(StripePaymentGateway::class)->parseWebhook($request))
        ->toThrow(PaymentException::class);
});

it('rejects a Stripe webhook whose timestamp is too old (replay)', function () {
    $request = stripeWebhookRequest(
        ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid']]],
        timestamp: now()->timestamp - 3600,
    );

    expect(fn () => app(StripePaymentGateway::class)->parseWebhook($request))
        ->toThrow(PaymentException::class);
});

it('throws a typed exception when Stripe is not configured', function () {
    config()->set('payment.drivers.stripe.secret_key', null);

    expect(fn () => app(StripePaymentGateway::class)->createCheckout(stripePendingPayment()))
        ->toThrow(PaymentException::class);
});

it('treats a completed-but-unpaid session as an async payment in progress', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'unpaid']],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Processing);
});

it('maps async_payment_succeeded to paid', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'checkout.session.async_payment_succeeded',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid']],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Paid);
});

it('maps an expired checkout session to expired', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'checkout.session.expired',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'unpaid']],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Expired);
});

it('maps a FULL charge.refunded event to refunded and carries our payment id from the charge metadata', function () {
    Http::fake();

    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_1', 'refunded' => true, 'amount' => 33300, 'amount_refunded' => 33300, 'metadata' => ['payment_id' => '77'], 'payment_intent' => 'pi_1']],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Refunded)
        ->and($event->clientReference)->toBe('77');
    Http::assertNothingSent(); // la Charge porte deja notre id (metadata heritees du PaymentIntent)
});

it('does NOT deactivate on a PARTIAL charge.refunded (coverage stays, handled manually)', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_1', 'refunded' => false, 'amount' => 33300, 'amount_refunded' => 1000, 'metadata' => ['payment_id' => '77']]],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Unresolved);
});

it('treats a fully-refunded charge as Refunded even when the refunded flag is false (amount reconciliation)', function () {
    // amount_refunded >= amount alors que le booleen refunded n'est pas (encore) a true : c'est bien un
    // remboursement integral -> Refunded.
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_1', 'refunded' => false, 'amount' => 33300, 'amount_refunded' => 33300, 'metadata' => ['payment_id' => '77']]],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Refunded);
});

/** Un litige tel que Stripe l'envoie vraiment : metadata VIDES, seulement la Charge et le PaymentIntent. */
function stripeDisputeEvent(string $type, string $status, ?string $paymentIntent = 'pi_1'): Request
{
    return stripeWebhookRequest([
        'type' => $type,
        'data' => ['object' => ['id' => 'du_1', 'object' => 'dispute', 'status' => $status, 'metadata' => [], 'charge' => 'ch_1', 'payment_intent' => $paymentIntent]],
    ]);
}

it('does NOT deactivate on a dispute being opened, and asks Stripe nothing (funds only held, may be won)', function () {
    Http::fake();

    $event = app(StripePaymentGateway::class)->parseWebhook(stripeDisputeEvent('charge.dispute.created', 'needs_response'));

    expect($event->outcome)->toBe(PaymentEventOutcome::Unresolved);
    Http::assertNothingSent();
});

it('does NOT deactivate on a dispute won, and asks Stripe nothing (dispute closed in the merchant favour)', function () {
    Http::fake();

    $event = app(StripePaymentGateway::class)->parseWebhook(stripeDisputeEvent('charge.dispute.closed', 'won'));

    expect($event->outcome)->toBe(PaymentEventOutcome::Unresolved);
    Http::assertNothingSent();
});

it('deactivates (refunded) when a dispute is lost, finding our payment id on its payment intent', function () {
    Http::fake(['*/v1/payment_intents/pi_1' => Http::response(['id' => 'pi_1', 'metadata' => ['payment_id' => '77']])]);

    $event = app(StripePaymentGateway::class)->parseWebhook(stripeDisputeEvent('charge.dispute.closed', 'lost'));

    expect($event->outcome)->toBe(PaymentEventOutcome::Refunded)
        ->and($event->clientReference)->toBe('77');
    Http::assertSentCount(1);
    Http::assertSent(fn ($req) => $req->method() === 'GET' && str_ends_with($req->url(), '/v1/payment_intents/pi_1')
        && $req->hasHeader('Authorization', 'Bearer sk_test_x'));
});

it('falls back on the charge of a lost dispute that has no payment intent', function () {
    Http::fake(['*/v1/charges/ch_1' => Http::response(['id' => 'ch_1', 'metadata' => ['payment_id' => '77']])]);

    $event = app(StripePaymentGateway::class)->parseWebhook(stripeDisputeEvent('charge.dispute.closed', 'lost', paymentIntent: null));

    expect($event->clientReference)->toBe('77');
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v1/charges/ch_1'));
});

it('raises a payment error when Stripe cannot tell which payment a lost dispute is about', function () {
    Http::fake(['*/v1/payment_intents/*' => Http::response(['error' => ['message' => 'down']], 500)]);

    app(StripePaymentGateway::class)->parseWebhook(stripeDisputeEvent('charge.dispute.closed', 'lost'));
})->throws(PaymentException::class);

it('reports an expired session as expired when polling', function () {
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'status' => 'expired', 'payment_status' => 'unpaid'])]);

    $payment = stripePendingPayment();
    $payment->update(['provider_reference' => 'cs_1']);

    expect(app(StripePaymentGateway::class)->checkStatus($payment)->outcome)->toBe(PaymentEventOutcome::Expired);
});

it('reports a completed-but-unpaid session as processing when polling', function () {
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'unpaid'])]);

    $payment = stripePendingPayment();
    $payment->update(['provider_reference' => 'cs_1']);

    expect(app(StripePaymentGateway::class)->checkStatus($payment)->outcome)->toBe(PaymentEventOutcome::Processing);
});
