<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Data\Admin\QuizResultListResult;
use App\Enums\Quiz\QuizOutcome;
use App\Repositories\Admin\QuizResultListRepository;

/**
 * Liste des reponses au quiz d'eligibilite, pour le back-office (lecture seule).
 *
 * Plan de requetes (verifie par un test de budget) : 3.
 *   1. nombre de reponses par issue (les trois compteurs, en une requete groupee)
 *   2-3. nombre de reponses filtrees et page de la liste
 * Liste vide : 2 (la page n'est pas lue).
 */
final readonly class QuizResultListService
{
    public function __construct(private QuizResultListRepository $results) {}

    public function list(?QuizOutcome $outcome, int $perPage): QuizResultListResult
    {
        $counted = $this->results->countByOutcome();
        $counts = [];

        foreach (QuizOutcome::cases() as $case) {
            $counts[$case->value] = $counted[$case->value] ?? 0;
        }

        return new QuizResultListResult($this->results->paginate($outcome, $perPage), $counts);
    }
}
