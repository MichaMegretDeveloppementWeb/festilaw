<?php

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Actions\Web\Starter\StartRenewalPaymentAction;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Exceptions\Starter\StarterException;
use App\Mail\StarterPaymentConfirmed;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('records a late payment success but does NOT reactivate a cancelled dossier', function () {
    Mail::fake();

    $dossier = Submission::factory()->starter()->create(['status' => SubmissionStatus::Cancelled]);
    $payment = $dossier->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'service_year' => (int) now()->year,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_late',
        'status' => PaymentStatus::Pending,
    ]);

    app(MarkPaymentSucceededAction::class)->execute($payment);

    // Le paiement reflete la realite (argent recu), mais le dossier reste annule et inactif.
    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($dossier->fresh()->status)->toBe(SubmissionStatus::Cancelled)
        ->and($dossier->fresh()->isActive())->toBeFalse();

    // On ne confirme pas au client un service qui n'est pas actif.
    Mail::assertNotSent(StarterPaymentConfirmed::class);
});

it('does NOT downgrade a completed dossier to paid when a renewal succeeds', function () {
    Mail::fake();

    // Dossier deja termine (RP delivree) : la prestation est livree. Le renouvellement est un axe distinct.
    $dossier = Submission::factory()->starter()->paid()->create(['status' => SubmissionStatus::Completed]);
    $renewal = $dossier->payments()->create([
        'type' => PaymentType::AnnualRenewal,
        'amount_cents' => 33300,
        'service_year' => (int) now()->year + 1,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_renew',
        'status' => PaymentStatus::Pending,
    ]);

    app(MarkPaymentSucceededAction::class)->execute($renewal);

    // Termine reste Termine (orthogonal au renouvellement) et le dossier reste actif.
    expect($renewal->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($dossier->fresh()->status)->toBe(SubmissionStatus::Completed)
        ->and($dossier->fresh()->isActive())->toBeTrue();
});

it('refuses to start a renewal payment on a cancelled dossier', function () {
    $dossier = Submission::factory()->starter()->paid()->create(['status' => SubmissionStatus::Cancelled]);

    app(StartRenewalPaymentAction::class)->execute($dossier, 'stripe');
})->throws(StarterException::class);
