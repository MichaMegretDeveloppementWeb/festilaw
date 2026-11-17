<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogGeneration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le sujet d'un article genere, du point de vue des generations suivantes.
 */
final class BlogGenerationTopicRepository
{
    /**
     * Marque refuse le sujet de la generation qui a cree cet article (cahier des charges, § 5) : supprimer un
     * article genere dit que son sujet ne doit plus etre propose. A appeler avant la suppression, dans sa
     * transaction : la cle de la generation vers l'article se vide quand l'article part.
     */
    public function rejectTopicOf(int $postId, ?int $generationId): void
    {
        BlogGeneration::query()
            ->where(fn (Builder $query) => $query->where('blog_post_id', $postId)->when($generationId !== null, fn (Builder $query) => $query->orWhere('id', $generationId)))
            ->whereNull('topic_rejected_at')
            ->update(['topic_rejected_at' => CarbonImmutable::now()]);
    }
}
