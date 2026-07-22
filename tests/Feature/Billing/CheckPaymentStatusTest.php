<?php

use App\Actions\Web\Payment\CheckPaymentStatusAction;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    app()->forgetInstance(PaymentGatewayRegistry::class);
    Mail::fake();
});

/** A payment in the given status tied to a Stripe checkout session. */
function stripePaymentInStatus(PaymentStatus $status, SubmissionStatus $submissionStatus = SubmissionStatus::AwaitingPayment): Payment
{
    $submission = Submission::factory()->starter()->create(['status' => $submissionStatus]);

    return $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'service_year' => 2026,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => $status,
    ]);
}

it('corrects a false failure: a failed payment Stripe reports as paid becomes succeeded and reactivates the dossier', function () {
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'paid'])]);
    $payment = stripePaymentInStatus(PaymentStatus::Failed);

    $result = app(CheckPaymentStatusAction::class)->execute($payment);

    expect($result->corrected)->toBeTrue()
        ->and($result->confirmedPaid())->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->submission->fresh()->status)->toBe(SubmissionStatus::Paid);
});

it('leaves a genuinely failed payment failed when Stripe still reports it unpaid', function () {
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'unpaid'])]);
    $payment = stripePaymentInStatus(PaymentStatus::Failed);

    $result = app(CheckPaymentStatusAction::class)->execute($payment);

    expect($result->corrected)->toBeFalse()
        ->and($result->confirmedPaid())->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

it('reports the provider as unreachable when its gateway is not enabled', function () {
    config()->set('payment.enabled', []); // stripe absent du registre
    app()->forgetInstance(PaymentGatewayRegistry::class);
    $payment = stripePaymentInStatus(PaymentStatus::Failed);

    $result = app(CheckPaymentStatusAction::class)->execute($payment);

    expect($result->reachable)->toBeFalse()
        ->and($result->corrected)->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

it('does not re-query an already settled (succeeded) payment', function () {
    Http::fake();
    $payment = stripePaymentInStatus(PaymentStatus::Succeeded, SubmissionStatus::Paid);

    $result = app(CheckPaymentStatusAction::class)->execute($payment);

    expect($result->corrected)->toBeFalse()
        ->and($result->confirmedPaid())->toBeTrue();
    Http::assertNothingSent();
});
