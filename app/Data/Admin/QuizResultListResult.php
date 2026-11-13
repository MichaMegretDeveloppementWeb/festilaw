<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Models\QuizResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Resultat de la liste des reponses au quiz du back-office : la page de reponses et le nombre de
 * reponses par issue, toutes issues presentes (zero compris).
 */
final readonly class QuizResultListResult
{
    /**
     * @param  LengthAwarePaginator<int, QuizResult>  $results
     * @param  array<string, int>  $counts  valeur de QuizOutcome => nombre de reponses
     */
    public function __construct(
        public LengthAwarePaginator $results,
        public array $counts,
    ) {}
}
