<?php

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Payment;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A dossier with a single subscription payment in the given status. */
function dossierWithPayment(PaymentStatus $status, PaymentType $type = PaymentType::StarterSubscription): Submission
{
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid]);
    $submission->payments()->create([
        'type' => $type,
        'amount_cents' => 33300,
        'service_year' => 2026,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'ref',
        'status' => $status,
        'paid_at' => $status === PaymentStatus::Succeeded ? now() : null,
    ]);

    return $submission->fresh();
}

it('is active with a succeeded subscription payment', function () {
    expect(dossierWithPayment(PaymentStatus::Succeeded)->isActive())->toBeTrue();
});

it('is NOT active once the subscription payment is refunded', function () {
    $dossier = dossierWithPayment(PaymentStatus::Refunded);

    // Statut stocke encore "Payé" (cache) mais l'etat actif DERIVE tombe a faux : le remboursement
    // desactive le dossier de lui-meme.
    expect($dossier->status)->toBe(SubmissionStatus::Paid)
        ->and($dossier->isActive())->toBeFalse();
});

it('is NOT active with only a pending or failed payment', function () {
    expect(dossierWithPayment(PaymentStatus::Pending)->isActive())->toBeFalse()
        ->and(dossierWithPayment(PaymentStatus::Failed)->isActive())->toBeFalse();
});

it('is NOT active when the only succeeded payment is a one-off audit (not a subscription)', function () {
    expect(dossierWithPayment(PaymentStatus::Succeeded, PaymentType::ScaleAudit)->isActive())->toBeFalse();
});

it('is NOT active when explicitly cancelled, even with a succeeded payment', function () {
    $dossier = dossierWithPayment(PaymentStatus::Succeeded);
    $dossier->update(['status' => SubmissionStatus::Cancelled]);

    expect($dossier->fresh()->isActive())->toBeFalse();
});

it('scopeActive returns only dossiers with a succeeded non-refunded subscription payment', function () {
    $active = dossierWithPayment(PaymentStatus::Succeeded);
    dossierWithPayment(PaymentStatus::Refunded);
    dossierWithPayment(PaymentStatus::Pending);

    $ids = Submission::query()->active()->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids->first())->toBe($active->id);
});
