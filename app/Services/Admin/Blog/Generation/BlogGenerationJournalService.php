<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog\Generation;

use App\Data\Admin\Blog\Generation\BlogGenerationJournalRow;
use App\Models\BlogGeneration;
use App\Repositories\Blog\AdminBlogGenerationJournalRepository;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogGenerationLabels;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Journal des generations du back-office.
 *
 * Plan de requetes (verifie par un test de budget) : 3.
 *   1-2. nombre de generations et page du journal
 *   3. jetons, modeles et etape en echec des generations de la page
 * Journal vide : 1 (le nombre seul).
 */
final readonly class BlogGenerationJournalService
{
    public function __construct(
        private AdminBlogGenerationJournalRepository $journal,
        private BlogGenerationPresenter $presenter,
        private BlogGenerationLabels $labels,
        private BlogAiCostFormatter $costs,
    ) {}

    /** @return LengthAwarePaginator<int, BlogGenerationJournalRow> */
    public function page(int $perPage): LengthAwarePaginator
    {
        $page = $this->journal->paginate($perPage);
        $generations = collect($page->items());
        $totals = $this->journal->stepTotalsOf($generations->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

        $page->setCollection($generations->map(function (BlogGeneration $generation) use ($totals): BlogGenerationJournalRow {
            $steps = $totals->get($generation->id);

            return new BlogGenerationJournalRow(
                id: $generation->id,
                createdAt: $generation->created_at->toImmutable(),
                trigger: $generation->trigger,
                status: $generation->status,
                outcome: $this->labels->outcome($generation->outcome_reason, $steps->failed_step ?? null),
                subject: $this->presenter->subject($generation->topic_title, $generation->topic_hint),
                models: array_values(array_filter(explode(',', (string) ($steps->models ?? '')))),
                inputTokens: (int) ($steps->input_tokens ?? 0),
                outputTokens: (int) ($steps->output_tokens ?? 0),
                cost: $this->costs->dollars($generation->cost_microusd),
                duration: $generation->finished_at === null ? null : $this->presenter->elapsed($generation->started_at, $generation->finished_at),
            );
        }));

        return $page;
    }
}
