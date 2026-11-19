<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog\Generation;

use App\Data\Admin\Blog\Generation\BlogGenerationJournalRow;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Models\BlogGeneration;
use App\Repositories\Blog\AdminBlogGenerationJournalRepository;
use App\Repositories\Blog\FailedBlogGenerationStepRepository;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogGenerationLabels;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Les generations en liste : une page de l'historique, ou les dernieres pour la vue d'ensemble.
 *
 * Plan de requetes de l'historique (verifie par un test de budget) : 3.
 *   1-2. nombre de generations et page
 *   3. etape en echec des generations de la page
 * Historique vide : 1 (le nombre seul). Les dernieres generations : 2, ou 1 s'il n'y en a aucune.
 */
final readonly class BlogGenerationJournalService
{
    public function __construct(
        private AdminBlogGenerationJournalRepository $journal,
        private FailedBlogGenerationStepRepository $failedSteps,
        private BlogGenerationPresenter $presenter,
        private BlogGenerationLabels $labels,
        private BlogAiCostFormatter $costs,
    ) {}

    /** @return LengthAwarePaginator<int, BlogGenerationJournalRow> */
    public function page(int $perPage): LengthAwarePaginator
    {
        $page = $this->journal->paginate($perPage);

        $page->setCollection($this->rows(collect($page->items())));

        return $page;
    }

    /** @return list<BlogGenerationJournalRow> */
    public function latest(int $limit): array
    {
        return $this->rows($this->journal->latest($limit))->all();
    }

    /**
     * @param  Collection<int, BlogGeneration>  $generations
     * @return Collection<int, BlogGenerationJournalRow>
     */
    private function rows(Collection $generations): Collection
    {
        $failedSteps = $this->failedSteps->stepByGeneration(
            $generations->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );

        return $generations->map(fn (BlogGeneration $generation): BlogGenerationJournalRow => new BlogGenerationJournalRow(
            id: $generation->id,
            createdAt: $generation->created_at->toImmutable(),
            trigger: $generation->trigger,
            status: $generation->status,
            // Une generation arretee le dit deja par sa pastille : « arretee avant la fin » n'ajouterait rien a la ligne.
            outcome: $generation->outcome_reason === BlogGenerationOutcomeReason::CancelledByUser
                ? null
                : $this->labels->outcome($generation->outcome_reason, $failedSteps[$generation->id] ?? null),
            subject: $this->presenter->subject($generation->getAttributes()['post_title'] ?? null, $generation->topic_title, $generation->topic_hint),
            postId: $generation->blog_post_id,
            cost: $this->costs->dollars($generation->cost_microusd),
            duration: $generation->finished_at === null ? null : $this->presenter->elapsed($generation->started_at, $generation->finished_at),
        ));
    }
}
