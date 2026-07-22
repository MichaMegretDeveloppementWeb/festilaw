<?php

use App\Data\Admin\SubmissionListFilters;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\DossierState;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Payment;
use App\Models\Submission;
use App\Repositories\Admin\SubmissionListRepository;
use App\Services\Billing\RenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A dossier with the given workflow status and, optionally, a succeeded subscription payment for $serviceYear. */
function listDossier(SubmissionStatus $status, ?int $serviceYear): Submission
{
    $submission = Submission::factory()->starter()->create(['status' => $status]);

    if ($serviceYear !== null) {
        Payment::factory()->succeeded()->for($submission)->create([
            'type' => PaymentType::StarterSubscription,
            'service_year' => $serviceYear,
        ]);
    }

    return $submission->fresh();
}

it('keeps the state filter and the per-row badge in perfect lockstep (no divergence)', function () {
    $currentYear = (int) now()->year;

    // Un dossier representatif de CHAQUE etat, dont les deux cas "termine" (a jour ET en retard).
    listDossier(SubmissionStatus::InProgress, null);           // -> InProgress
    listDossier(SubmissionStatus::Paid, $currentYear);         // -> Active
    listDossier(SubmissionStatus::Paid, $currentYear - 1);     // -> Renewal (du/en retard)
    $completedCurrent = listDossier(SubmissionStatus::Completed, $currentYear);     // -> Completed
    $completedOld = listDossier(SubmissionStatus::Completed, $currentYear - 1);     // -> Renewal (termine mais du)
    listDossier(SubmissionStatus::Cancelled, $currentYear);    // -> Cancelled

    // Etat derive (badge) attendu par ligne -> cle de filtre correspondante.
    $renewals = app(RenewalService::class);
    $filterKeyFor = fn (DossierState $state): string => match ($state) {
        DossierState::InProgress => 'in_progress',
        DossierState::Active => 'active',
        DossierState::RenewalDue, DossierState::RenewalOverdue => 'renewal',
        DossierState::Completed => 'completed',
        DossierState::Cancelled => 'cancelled',
    };

    $expectedByFilter = [];
    foreach (Submission::all() as $dossier) {
        $expectedByFilter[$filterKeyFor($renewals->state($dossier))][] = $dossier->id;
    }

    // Garde anti-regression B1 : le "termine en retard" tombe dans "renewal", pas "completed".
    expect($expectedByFilter['renewal'] ?? [])->toContain($completedOld->id)
        ->and($expectedByFilter['completed'] ?? [])->toContain($completedCurrent->id);

    // Pour CHAQUE filtre : l'ensemble retourne par le repository == {dossiers dont le badge = ce filtre}.
    // C'est le contrat de coherence qui empeche filtre et badge de diverger (ce que B1 avait rate).
    $repository = app(SubmissionListRepository::class);
    $filters = ['in_progress', 'active', 'renewal', 'completed', 'cancelled'];

    $returnedByFilter = [];
    $normalisedExpected = [];
    foreach ($filters as $filter) {
        $dto = new SubmissionListFilters(contactsMode: false, type: '', state: $filter, search: '');
        $returnedByFilter[$filter] = $repository->paginate($dto, 50, $currentYear)->pluck('id')->sort()->values()->all();
        $normalisedExpected[$filter] = collect($expectedByFilter[$filter] ?? [])->sort()->values()->all();
    }

    expect($returnedByFilter)->toBe($normalisedExpected);
});
