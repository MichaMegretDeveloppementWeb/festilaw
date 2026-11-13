<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Data\Web\Blog\BlogArticleRow;
use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;

/**
 * Lecture de la PAGE d'un article publie, dans une langue. Une seule requete rend l'article, son texte
 * et le nom de sa categorie (jointures de un pour un) ; le contenu HTML n'est charge que pour la langue
 * demandee.
 *
 * Ne pas reutiliser pour les listes (PublishedBlogPostCardRepository) : elle charge le contenu entier.
 */
final readonly class PublishedBlogPostPageRepository
{
    /** L'article publie qui porte ce slug dans cette langue, avec son texte. Null sinon. */
    public function findBySlug(string $locale, string $slug): ?BlogArticleRow
    {
        $row = BlogPost::query()->toBase()
            ->join('blog_post_translations', function (JoinClause $join) use ($locale, $slug): void {
                $join->on('blog_post_translations.blog_post_id', '=', 'blog_posts.id')
                    ->where('blog_post_translations.locale', '=', $locale)
                    ->where('blog_post_translations.slug', '=', $slug);
            })
            ->leftJoin('blog_category_translations', function (JoinClause $join) use ($locale): void {
                $join->on('blog_category_translations.blog_category_id', '=', 'blog_posts.blog_category_id')
                    ->where('blog_category_translations.locale', '=', $locale);
            })
            ->where('blog_posts.status', BlogPostStatus::Published->value)
            ->first([
                'blog_category_translations.name as category_name',
                'blog_category_translations.slug as category_slug',
                'blog_posts.id',
                'blog_posts.blog_category_id',
                'blog_posts.featured_image_id',
                'blog_posts.share_image_id',
                'blog_posts.published_at',
                'blog_posts.content_updated_at',
                'blog_posts.sources',
                'blog_post_translations.title',
                'blog_post_translations.slug',
                'blog_post_translations.excerpt',
                'blog_post_translations.content_html',
                'blog_post_translations.meta_title',
                'blog_post_translations.meta_description',
            ]);

        if ($row === null) {
            return null;
        }

        $sources = is_string($row->sources) ? json_decode($row->sources, true) : null;

        return new BlogArticleRow(
            postId: (int) $row->id,
            categoryId: $row->blog_category_id === null ? null : (int) $row->blog_category_id,
            featuredImageId: $row->featured_image_id === null ? null : (int) $row->featured_image_id,
            shareImageId: $row->share_image_id === null ? null : (int) $row->share_image_id,
            publishedAt: CarbonImmutable::parse($row->published_at, 'UTC'),
            contentUpdatedAt: $row->content_updated_at === null ? null : CarbonImmutable::parse($row->content_updated_at, 'UTC'),
            sources: is_array($sources) ? array_values($sources) : [],
            title: (string) $row->title,
            slug: (string) $row->slug,
            excerpt: $row->excerpt,
            contentHtml: (string) $row->content_html,
            metaTitle: $row->meta_title,
            metaDescription: $row->meta_description,
            categoryName: $row->category_name,
            categorySlug: $row->category_slug,
        );
    }

    /**
     * Le slug de l'article dans chaque langue, pour ses equivalents (hreflang, selecteur de langue).
     *
     * @return array<string, string> locale => slug
     */
    public function slugsByLocale(int $postId): array
    {
        return BlogPostTranslation::query()
            ->where('blog_post_id', $postId)
            ->whereNotNull('slug')
            ->pluck('slug', 'locale')
            ->all();
    }
}
