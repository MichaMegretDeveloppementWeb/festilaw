<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Journal des generations du back-office : une page de generations, les plus recentes d'abord, et ce que
 * leurs etapes ont consomme (une requete groupee pour toute la page, jamais une par ligne).
 */
final readonly class AdminBlogGenerationJournalRepository
{
    /** @return LengthAwarePaginator<int, BlogGeneration> */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return BlogGeneration::query()
            ->select([
                'id', 'trigger', 'status', 'outcome_reason', 'topic_title', 'topic_hint', 'cost_microusd',
                'started_at', 'finished_at', 'created_at',
            ])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Jetons et modeles des etapes, et etape en echec s'il y en a une, par generation.
     *
     * @param  list<int>  $generationIds
     * @return Collection<int, object{blog_generation_id: int, input_tokens: int|string, output_tokens: int|string, models: string|null, failed_step: string|null}>
     */
    public function stepTotalsOf(array $generationIds): Collection
    {
        if ($generationIds === []) {
            return collect();
        }

        return BlogGenerationStep::query()
            ->toBase()
            ->whereIn('blog_generation_id', $generationIds)
            ->groupBy('blog_generation_id')
            ->selectRaw(
                'blog_generation_id, SUM(input_tokens) AS input_tokens, SUM(output_tokens) AS output_tokens, '
                .'GROUP_CONCAT(DISTINCT model) AS models, MAX(CASE WHEN status = ? THEN step END) AS failed_step',
                [BlogGenerationStepStatus::Failed->value],
            )
            ->get()
            ->keyBy('blog_generation_id');
    }
}
