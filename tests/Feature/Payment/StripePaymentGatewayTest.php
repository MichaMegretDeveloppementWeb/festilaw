<?php

use App\Data\Payment\CheckoutSessionData;
use App\Enums\Payment\PaymentEventOutcome;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Exceptions\Payment\PaymentException;
use App\Models\Payment;
use App\Models\Submission;
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

it('maps a charge.refunded event to refunded and carries our payment id from the charge metadata', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_1', 'metadata' => ['payment_id' => '77']]],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Refunded)
        ->and($event->clientReference)->toBe('77');
});

it('does NOT deactivate on a dispute being opened (funds only held, may be won)', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'charge.dispute.created',
        'data' => ['object' => ['id' => 'dp_1', 'status' => 'needs_response', 'metadata' => ['payment_id' => '77']]],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Unresolved);
});

it('does NOT deactivate on a dispute won (dispute closed in the merchant favour)', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'charge.dispute.closed',
        'data' => ['object' => ['id' => 'dp_1', 'status' => 'won', 'metadata' => ['payment_id' => '77']]],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Unresolved);
});

it('deactivates (refunded) only when a dispute is lost, funds definitively taken back', function () {
    $event = app(StripePaymentGateway::class)->parseWebhook(stripeWebhookRequest([
        'type' => 'charge.dispute.closed',
        'data' => ['object' => ['id' => 'dp_1', 'status' => 'lost', 'metadata' => ['payment_id' => '77']]],
    ]));

    expect($event->outcome)->toBe(PaymentEventOutcome::Refunded)
        ->and($event->clientReference)->toBe('77');
});

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
