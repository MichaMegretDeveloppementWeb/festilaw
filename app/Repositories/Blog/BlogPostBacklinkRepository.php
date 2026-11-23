<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogPostRetiredSlug;
use App\Models\BlogPostTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Les liens des autres articles vers un article (controle du 20/11) : ses adresses, actuelles et anciennes, et
 * les versions d'articles dont le HTML en contient une. Deux requetes pour les adresses, une pour les versions,
 * leurs articles et leurs images compris.
 */
final readonly class BlogPostBacklinkRepository
{
    /** @return list<array{locale: string, slug: string}> */
    public function slugsOf(int $postId): array
    {
        $live = BlogPostTranslation::query()->where('blog_post_id', $postId)->whereNotNull('slug')->get(['locale', 'slug']);
        $retired = BlogPostRetiredSlug::query()->where('blog_post_id', $postId)->get(['locale', 'slug']);

        return $live->concat($retired)
            ->map(static fn ($row): array => ['locale' => (string) $row->locale, 'slug' => (string) $row->slug])
            ->unique(static fn (array $slug): string => $slug['locale'].'/'.$slug['slug'])
            ->values()
            ->all();
    }

    /**
     * Les versions des autres articles dont le HTML mentionne une de ces adresses d'article. Un premier tri large
     * (le slug dans le HTML), que l'appelant affine lien par lien.
     *
     * @param  list<string>  $slugs
     * @return Collection<int, BlogPostTranslation>
     */
    public function translationsMentioning(int $postId, array $slugs): Collection
    {
        if ($slugs === []) {
            return new Collection;
        }

        return BlogPostTranslation::query()
            ->with('post.images')
            ->where('blog_post_id', '!=', $postId)
            ->where(static function (Builder $query) use ($slugs): void {
                foreach ($slugs as $slug) {
                    $query->orWhere('content_html', 'like', '%/blog/'.str_replace(['%', '_'], ['\%', '\_'], $slug).'%');
                }
            })
            ->get();
    }
}
