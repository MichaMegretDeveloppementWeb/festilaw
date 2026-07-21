<?php

use App\Enums\Billing\RenewalStatus;
use App\Enums\Payment\PaymentType;
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
