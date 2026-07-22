<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Enums\Submission\DossierState;
use App\Models\Submission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Resultat du listing back-office : la page de dossiers et l'etat derive (badge) de chaque ligne.
 */
final readonly class SubmissionListResult
{
    /**
     * @param  LengthAwarePaginator<Submission>  $submissions
     * @param  array<int, DossierState>  $dossierStates
     */
    public function __construct(
        public LengthAwarePaginator $submissions,
        public array $dossierStates,
    ) {}
}
