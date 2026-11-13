<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

use Carbon\CarbonImmutable;

/**
 * Ligne lue en base pour la page d'un article, dans une langue : l'article et son texte, en une seule
 * lecture. Sert a la page publique (article publie) et a l'apercu du back-office (tout statut).
 */
final readonly class BlogArticleRow
{
    /**
     * @param  list<array{label: string, url: string}>  $sources
     */
    public function __construct(
        public int $postId,
        public ?int $categoryId,
        public ?int $featuredImageId,
        public ?int $shareImageId,
        public CarbonImmutable $publishedAt,
        public ?CarbonImmutable $contentUpdatedAt,
        public array $sources,
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public string $contentHtml,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $categoryName,
        public ?string $categorySlug,
    ) {}

    /**
     * Depuis la ligne rendue par la requete (colonnes de l'article, de son texte et de sa categorie).
     *
     * @param  CarbonImmutable|null  $publishedAtFallback  date affichee pour un article jamais publie (apercu)
     */
    public static function fromRecord(object $row, ?CarbonImmutable $publishedAtFallback = null): self
    {
        $sources = is_string($row->sources) ? json_decode($row->sources, true) : null;

        return new self(
            postId: (int) $row->id,
            categoryId: $row->blog_category_id === null ? null : (int) $row->blog_category_id,
            featuredImageId: $row->featured_image_id === null ? null : (int) $row->featured_image_id,
            shareImageId: $row->share_image_id === null ? null : (int) $row->share_image_id,
            publishedAt: $row->published_at === null
                ? ($publishedAtFallback ?? CarbonImmutable::now('UTC'))
                : CarbonImmutable::parse($row->published_at, 'UTC'),
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
     * Colonnes attendues par fromRecord(), pour une requete sur blog_posts jointe a
     * blog_post_translations et blog_category_translations.
     *
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
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
        ];
    }
}
