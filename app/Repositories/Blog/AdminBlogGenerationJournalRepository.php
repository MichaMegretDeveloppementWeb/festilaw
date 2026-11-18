<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogGeneration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Generations lues par le back-office : une page de l'historique, les dernieres pour la vue d'ensemble, et
 * une generation avec le nom des personnes qui l'ont lancee et arretee. Les plus recentes d'abord.
 */
final readonly class AdminBlogGenerationJournalRepository
{
    private const LIST_COLUMNS = [
        'id', 'trigger', 'status', 'outcome_reason', 'topic_title', 'topic_hint', 'blog_post_id', 'cost_microusd',
        'started_at', 'finished_at', 'created_at',
    ];

    /** @return LengthAwarePaginator<int, BlogGeneration> */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->newest()->paginate($perPage);
    }

    /** @return Collection<int, BlogGeneration> */
    public function latest(int $limit): Collection
    {
        return $this->newest()->limit($limit)->get();
    }

    /**
     * Une generation pour son ecran, avec le nom de qui l'a lancee (`requester_name`) et de qui l'a arretee
     * (`canceller_name`), en une seule requete. Ni ce qu'elle a produit, ni la cause technique d'un echec.
     */
    public function findWithPeople(int $generationId): ?BlogGeneration
    {
        return BlogGeneration::query()
            ->leftJoin('users as requesters', 'requesters.id', '=', 'blog_generations.requested_by')
            ->leftJoin('users as cancellers', 'cancellers.id', '=', 'blog_generations.cancel_requested_by')
            ->where('blog_generations.id', $generationId)
            ->first([
                'blog_generations.id', 'blog_generations.type', 'blog_generations.trigger', 'blog_generations.status',
                'blog_generations.outcome_reason', 'blog_generations.topic_title', 'blog_generations.topic_hint',
                'blog_generations.instructions', 'blog_generations.blog_post_id', 'blog_generations.payload',
                'blog_generations.cost_microusd', 'blog_generations.forecast_low_microusd', 'blog_generations.forecast_high_microusd',
                'blog_generations.started_at', 'blog_generations.finished_at',
                'blog_generations.cancel_requested_at', 'blog_generations.created_at',
                'requesters.name as requester_name', 'cancellers.name as canceller_name',
            ]);
    }

    /** @return Builder<BlogGeneration> */
    private function newest(): Builder
    {
        return BlogGeneration::query()->select(self::LIST_COLUMNS)->latest('id');
    }
}
