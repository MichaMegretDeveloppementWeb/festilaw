<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogCategoryTranslation;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use Illuminate\Database\Query\Builder;

/**
 * Lecture au service du sitemap : les adresses du blog, sans aucun contenu. Une requete par fichier du
 * sitemap, quel que soit le nombre d'articles.
 */
final readonly class BlogSitemapRepository
{
    /**
     * La date de modification la plus recente des articles publies (la date de publication quand le
     * contenu n'a pas change depuis), et si l'un d'eux est range dans une categorie. Null quand aucun
     * article n'est publie.
     *
     * @return array{latest_modification: string, has_category: bool}|null
     */
    public function publishedPostSummary(): ?array
    {
        $row = BlogPost::query()->toBase()
            ->where('status', BlogPostStatus::Published->value)
            ->selectRaw('MAX(COALESCE(content_updated_at, published_at)) AS latest_modification')
            ->selectRaw('MAX(CASE WHEN blog_category_id IS NULL THEN 0 ELSE 1 END) AS has_category')
            ->first();

        if ($row === null || $row->latest_modification === null) {
            return null;
        }

        return [
            'latest_modification' => (string) $row->latest_modification,
            'has_category' => (int) $row->has_category === 1,
        ];
    }

    /**
     * Le slug de chaque article publie dans chaque langue (une ligne par article et par langue), avec
     * ses dates, du plus recent au plus ancien.
     *
     * @return list<array{post_id: int, locale: string, slug: string, published_at: string, content_updated_at: ?string}>
     */
    public function publishedPostSlugs(): array
    {
        return BlogPostTranslation::query()->toBase()
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
            ->where('blog_posts.status', BlogPostStatus::Published->value)
            ->whereNotNull('blog_post_translations.slug')
            ->orderByDesc('blog_posts.published_at')
            ->orderByDesc('blog_posts.id')
            ->get([
                'blog_posts.id as post_id',
                'blog_post_translations.locale',
                'blog_post_translations.slug',
                'blog_posts.published_at',
                'blog_posts.content_updated_at',
            ])
            ->map(static fn (object $row): array => [
                'post_id' => (int) $row->post_id,
                'locale' => (string) $row->locale,
                'slug' => (string) $row->slug,
                'published_at' => (string) $row->published_at,
                'content_updated_at' => $row->content_updated_at,
            ])
            ->all();
    }

    /**
     * Le slug, dans chaque langue, des categories qui ont au moins un article publie.
     *
     * @return list<array{category_id: int, locale: string, slug: string}>
     */
    public function categorySlugsWithPublishedPosts(): array
    {
        return BlogCategoryTranslation::query()->toBase()
            ->join('blog_categories', 'blog_categories.id', '=', 'blog_category_translations.blog_category_id')
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('blog_posts')
                    ->whereColumn('blog_posts.blog_category_id', 'blog_categories.id')
                    ->where('blog_posts.status', BlogPostStatus::Published->value);
            })
            ->orderBy('blog_categories.position')
            ->orderBy('blog_categories.id')
            ->get(['blog_categories.id as category_id', 'blog_category_translations.locale', 'blog_category_translations.slug'])
            ->map(static fn (object $row): array => [
                'category_id' => (int) $row->category_id,
                'locale' => (string) $row->locale,
                'slug' => (string) $row->slug,
            ])
            ->all();
    }
}
