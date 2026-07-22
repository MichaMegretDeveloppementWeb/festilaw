<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Data\Admin\SubmissionListFilters;
use App\Data\Admin\SubmissionListResult;
use App\Models\Submission;
use App\Repositories\Admin\SubmissionListRepository;
use App\Services\Billing\RenewalService;

/**
 * Orchestration (lecture) de la liste back-office : recupere la page filtree via le repository, puis
 * derive l'etat affiche (badge) de chaque ligne via RenewalService. Le composant Livewire ne fait
 * ainsi ni requete ni derivation. Transaction-agnostique (lecture seule).
 */
final readonly class SubmissionListService
{
    public function __construct(
        private SubmissionListRepository $repository,
        private RenewalService $renewals,
    ) {}

    public function list(SubmissionListFilters $filters, int $perPage, int $currentYear): SubmissionListResult
    {
        $submissions = $this->repository->paginate($filters, $perPage, $currentYear);

        $states = [];
        foreach ($submissions->getCollection() as $submission) {
            /** @var Submission $submission */
            $states[$submission->id] = $this->renewals->state($submission);
        }

        return new SubmissionListResult($submissions, $states);
    }
}
