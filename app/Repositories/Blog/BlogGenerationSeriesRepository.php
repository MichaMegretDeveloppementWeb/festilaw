<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Models\BlogGeneration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * La serie en cours des generations d'articles terminees sans brouillon (echec, arret de securite, doublon,
 * aucun sujet), comptee depuis la derniere generation d'article reussie. Les traductions, les arrets a la
 * main et les lots retenus jamais lances (sans `started_at`) n'en font pas partie.
 */
final readonly class BlogGenerationSeriesRepository
{
    /** La derniere generation d'article qui a donne un brouillon, 0 s'il n'y en a jamais eu. */
    public function lastArticleWithDraftId(): int
    {
        return (int) BlogGeneration::query()
            ->where('type', BlogGenerationType::Article->value)
            ->where('status', BlogGenerationStatus::Completed->value)
            ->max('id');
    }

    public function countWithoutDraftAfter(int $generationId): int
    {
        return $this->withoutDraftAfter($generationId)->count();
    }

    /** @return Collection<int, BlogGeneration> les plus recentes d'abord */
    public function latestWithoutDraftAfter(int $generationId, int $limit): Collection
    {
        return $this->withoutDraftAfter($generationId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'status', 'outcome_reason', 'topic_title', 'topic_hint']);
    }

    /** @return Builder<BlogGeneration> */
    private function withoutDraftAfter(int $generationId): Builder
    {
        return BlogGeneration::query()
            ->where('type', BlogGenerationType::Article->value)
            ->where('id', '>', $generationId)
            ->whereNotNull('started_at')
            ->whereIn('status', [BlogGenerationStatus::Failed->value, BlogGenerationStatus::Abandoned->value]);
    }
}
