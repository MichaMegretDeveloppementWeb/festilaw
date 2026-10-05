<?php

declare(strict_types=1);

use App\Actions\Web\Payment\CheckPaymentStatusAction;
use App\Actions\Web\Payment\MarkPaymentRefundedAction;
use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Mail\PaymentNeedsReview;
use App\Mail\ScaleAuditConfirmed;
use App\Mail\StarterPaymentConfirmed;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
 | Garde-fou des paiements : une confirmation n'est jamais bloquee (Stripe fait foi, l'argent est recu), mais
 | un double paiement ou un montant encaisse different du montant attendu alerte Festilaw ; un client qui a
 | paye deux fois ne recoit pas de seconde confirmation.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    config()->set('festilaw.notification_email', 'team@example.com');
    app()->forgetInstance(PaymentGatewayRegistry::class);
    app()->forgetInstance(StripePaymentGateway::class);
    Mail::fake();
});

/** Un paiement Stripe du dossier, dans l'etat voulu. */
function stripePaymentFor(Submission $dossier, PaymentStatus $status, PaymentType $type = PaymentType::StarterSubscription, ?int $year = 2026, int $cents = 33300, string $reference = 'cs_new'): Payment
{
    return $dossier->payments()->create([
        'type' => $type,
        'amount_cents' => $cents,
        'service_year' => $year,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => $reference,
        'status' => $status,
        'paid_at' => $status === PaymentStatus::Succeeded ? now()->subMonth() : null,
    ]);
}

/** Un webhook checkout.session.completed paye, signe, pour ce paiement. */
function postPaidCheckoutWebhook(Payment $payment, ?int $amountTotal): void
{
    $object = ['id' => $payment->provider_reference, 'payment_status' => 'paid', 'client_reference_id' => (string) $payment->id];
    if ($amountTotal !== null) {
        $object['amount_total'] = $amountTotal;
    }
    $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => $object]]);
    $time = now()->timestamp;
    $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_x');

    test()->call('POST', '/webhooks/payment/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
    ], $payload)->assertNoContent();
}

it('records a payment Stripe confirms on an old failed one, but flags the double payment and spares the client a second confirmation', function () {
    $dossier = Submission::factory()->starter()->paid(2026)->create(['email' => 'client@example.com', 'locale' => 'en']);
    $failed = stripePaymentFor($dossier, PaymentStatus::Failed, reference: 'cs_old');
    Http::fake(['*/v1/checkout/sessions/cs_old' => Http::response(['id' => 'cs_old', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 33300])]);

    $result = app(CheckPaymentStatusAction::class)->execute($failed);

    expect($result->corrected)->toBeTrue()
        ->and($failed->fresh()->status)->toBe(PaymentStatus::Succeeded);
    Mail::assertSent(PaymentNeedsReview::class, fn (PaymentNeedsReview $mail) => $mail->hasTo('team@example.com')
        && $mail->review->isDuplicate()
        && ! $mail->review->amountMismatch()
        && str_contains($mail->render(), 'Paiement en double')
        && str_contains($mail->render(), $dossier->reference));
    Mail::assertNotSent(StarterPaymentConfirmed::class);
});

it('flags a charged amount that differs from the expected one, the client still being confirmed', function () {
    $dossier = Submission::factory()->starter()->create(['email' => 'client@example.com', 'locale' => 'en']);
    $payment = stripePaymentFor($dossier, PaymentStatus::Pending);

    postPaidCheckoutWebhook($payment, amountTotal: 9900);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
    Mail::assertSent(PaymentNeedsReview::class, fn (PaymentNeedsReview $mail) => $mail->review->amountMismatch()
        && ! $mail->review->isDuplicate()
        && str_contains($mail->render(), 'Stripe a encaissé 99,00 EUR alors que le site attendait 333,00 EUR'));
    Mail::assertSent(StarterPaymentConfirmed::class, fn (StarterPaymentConfirmed $mail) => $mail->hasTo('client@example.com'));
});

it('flags a Scale audit paid twice', function () {
    $dossier = Submission::factory()->scale()->create(['email' => 'scale@example.com', 'locale' => 'en']);
    stripePaymentFor($dossier, PaymentStatus::Succeeded, PaymentType::ScaleAudit, year: null, cents: 7500, reference: 'cs_audit_1');
    $second = stripePaymentFor($dossier, PaymentStatus::Pending, PaymentType::ScaleAudit, year: null, cents: 7500, reference: 'cs_audit_2');

    app(MarkPaymentSucceededAction::class)->execute($second, 'cs_audit_2', 7500);

    Mail::assertSent(PaymentNeedsReview::class, fn (PaymentNeedsReview $mail) => $mail->review->duplicateOf?->provider_reference === 'cs_audit_1');
    Mail::assertNotSent(ScaleAuditConfirmed::class);
});

it('raises no alert for a renewal of another year, a payment made again after a refund, or a replayed confirmation', function () {
    // Renouvellement 2027 d'un dossier paye en 2026, montant identique.
    $renewed = Submission::factory()->starter()->paid(2026)->create(['email' => 'renew@example.com']);
    $renewal = stripePaymentFor($renewed, PaymentStatus::Pending, PaymentType::AnnualRenewal, year: 2027, reference: 'cs_renew');
    postPaidCheckoutWebhook($renewal, amountTotal: 33300);
    postPaidCheckoutWebhook($renewal, amountTotal: 33300); // webhook rejoue : pas de nouvelle transition

    // Paiement 2026 rembourse, puis paye de nouveau.
    $refunded = Submission::factory()->starter()->create(['email' => 'again@example.com']);
    app(MarkPaymentRefundedAction::class)->execute(stripePaymentFor($refunded, PaymentStatus::Succeeded, reference: 'cs_first'));
    postPaidCheckoutWebhook(stripePaymentFor($refunded, PaymentStatus::Pending, reference: 'cs_second'), amountTotal: 33300);

    expect($renewal->fresh()->status)->toBe(PaymentStatus::Succeeded);
    Mail::assertNotSent(PaymentNeedsReview::class);
    Mail::assertSent(StarterPaymentConfirmed::class, 2);
});

it('raises no amount alert when the provider does not report the charged amount', function () {
    $payment = stripePaymentFor(Submission::factory()->starter()->create(), PaymentStatus::Pending);

    postPaidCheckoutWebhook($payment, amountTotal: null);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
    Mail::assertNotSent(PaymentNeedsReview::class);
});
