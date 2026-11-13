<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

use Carbon\CarbonImmutable;

/**
 * Ligne lue en base pour une carte d'article publie, dans une langue : exactement ce qu'une carte
 * affiche, rien de plus. Devient une BlogPostCardData une fois ses adresses et son image resolues.
 */
final readonly class BlogPostCardRow
{
    public function __construct(
        public int $postId,
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public CarbonImmutable $publishedAt,
        public ?int $featuredImageId,
        public ?string $categoryName,
        public ?string $categorySlug,
    ) {}
}
