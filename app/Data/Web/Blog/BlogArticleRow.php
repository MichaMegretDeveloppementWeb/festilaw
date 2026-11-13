<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

use Carbon\CarbonImmutable;

/**
 * Ligne lue en base pour la page d'un article publie, dans une langue : l'article et son texte, en
 * une seule lecture.
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
}
