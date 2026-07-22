<?php

use App\Enums\Billing\RenewalStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\DossierState;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Billing\RenewalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A paid dossier with one succeeded subscription payment covering $serviceYear. */
function paidDossier(int $serviceYear, PaymentType $type = PaymentType::StarterSubscription): Submission
{
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid]);
    Payment::factory()->succeeded()->for($submission)->create([
        'type' => $type,
        'service_year' => $serviceYear,
    ]);

    return $submission->fresh();
}

it('reports up-to-date within the paid service year', function () {
    $service = app(RenewalService::class);
    $dossier = paidDossier(2026);

    expect($service->paidThroughYear($dossier))->toBe(2026)
        ->and($service->status($dossier, CarbonImmutable::create(2026, 7, 1)))->toBe(RenewalStatus::UpToDate)
        ->and($service->dueYear($dossier, CarbonImmutable::create(2026, 7, 1)))->toBeNull()
        ->and($service->nextRenewalDate($dossier)->toDateString())->toBe('2027-01-01');
});

it('is due at the start of the next service year, then overdue past the grace window', function () {
    config()->set('festilaw.renewal.grace_days', 30);
    $service = app(RenewalService::class);
    $dossier = paidDossier(2026);

    // 2 janvier 2027 : renouvellement ouvert, dans la grace.
    expect($service->status($dossier, CarbonImmutable::create(2027, 1, 2)))->toBe(RenewalStatus::Due)
        ->and($service->dueYear($dossier, CarbonImmutable::create(2027, 1, 2)))->toBe(2027);

    // 5 fevrier 2027 : grace de 30 jours depassee (fin le 31 janvier).
    expect($service->status($dossier, CarbonImmutable::create(2027, 2, 5)))->toBe(RenewalStatus::Overdue);
});

it('counts the latest renewal payment as the paid-through year', function () {
    $service = app(RenewalService::class);
    $dossier = paidDossier(2026);
    Payment::factory()->succeeded()->for($dossier)->create([
        'type' => PaymentType::AnnualRenewal,
        'service_year' => 2027,
    ]);

    expect($service->paidThroughYear($dossier->fresh()))->toBe(2027)
        ->and($service->status($dossier->fresh(), CarbonImmutable::create(2027, 6, 1)))->toBe(RenewalStatus::UpToDate);
});

it('treats a never-paid dossier as not a renewal (null due year)', function () {
    $service = app(RenewalService::class);
    $dossier = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);

    expect($service->paidThroughYear($dossier))->toBeNull()
        ->and($service->dueYear($dossier, CarbonImmutable::create(2027, 6, 1)))->toBeNull()
        ->and($service->status($dossier, CarbonImmutable::create(2027, 6, 1)))->toBe(RenewalStatus::UpToDate);
});

it('derives the displayed dossier state from workflow + payments + renewal', function () {
    $service = app(RenewalService::class);
    $jan = CarbonImmutable::create(2026, 1, 10); // dans la fenetre de grace
    $mar = CarbonImmutable::create(2026, 3, 1);  // grace depassee

    // Pas de paiement -> en cours (pas encore actif).
    $inProgress = Submission::factory()->starter()->create(['status' => SubmissionStatus::InProgress]);
    expect($service->state($inProgress, $mar))->toBe(DossierState::InProgress);

    // Paye l'annee courante -> actif.
    expect($service->state(paidDossier(2026), $mar))->toBe(DossierState::Active);

    // Paye l'an dernier, dans la grace -> a renouveler ; grace depassee -> en retard.
    expect($service->state(paidDossier(2025), $jan))->toBe(DossierState::RenewalDue)
        ->and($service->state(paidDossier(2025), $mar))->toBe(DossierState::RenewalOverdue);

    // Annule : prioritaire sur tout le reste.
    $cancelled = paidDossier(2025);
    $cancelled->update(['status' => SubmissionStatus::Cancelled]);
    expect($service->state($cancelled->fresh(), $mar))->toBe(DossierState::Cancelled);

    // "Termine" (RP delivree) et renouvellement sont ORTHOGONAUX : "Termine" ne s'affiche que si le
    // dossier est a jour ; un dossier termine mais non renouvele reste "a renouveler / en retard".
    $completedUpToDate = paidDossier(2026);
    $completedUpToDate->update(['status' => SubmissionStatus::Completed]);
    expect($service->state($completedUpToDate->fresh(), $mar))->toBe(DossierState::Completed);

    $completedOverdue = paidDossier(2025);
    $completedOverdue->update(['status' => SubmissionStatus::Completed]);
    expect($service->state($completedOverdue->fresh(), $mar))->toBe(DossierState::RenewalOverdue);
});
