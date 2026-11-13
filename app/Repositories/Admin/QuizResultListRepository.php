<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Enums\Quiz\QuizOutcome;
use App\Models\QuizResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Acces BDD (lecture) de la liste des reponses au quiz du back-office. Ne sert qu'a cet ecran.
 */
final readonly class QuizResultListRepository
{
    /**
     * Page de reponses, les plus recentes d'abord, filtree par issue si demande. 2 requetes (nombre, page).
     *
     * @return LengthAwarePaginator<int, QuizResult>
     */
    public function paginate(?QuizOutcome $outcome, int $perPage): LengthAwarePaginator
    {
        return QuizResult::query()
            ->when($outcome !== null, fn (Builder $query) => $query->where('outcome', $outcome))
            ->latest()
            ->latest('id')
            ->paginate($perPage, [
                'id',
                'outcome',
                'q1_based_outside_eu',
                'q2_sells_to_eu',
                'q3_sells_restricted',
                'locale',
                'created_at',
            ]);
    }

    /**
     * Nombre de reponses par issue, en une requete. Une issue sans reponse est absente du tableau.
     *
     * @return array<string, int> valeur de QuizOutcome => nombre
     */
    public function countByOutcome(): array
    {
        return QuizResult::query()->toBase()
            ->selectRaw('outcome, count(*) as total')
            ->groupBy('outcome')
            ->pluck('total', 'outcome')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
    }
}
