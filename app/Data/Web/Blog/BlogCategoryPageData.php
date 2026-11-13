<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

/**
 * Tout ce que la page d'une categorie du blog affiche, dans une langue.
 */
final readonly class BlogCategoryPageData
{
    /**
     * @param  list<BlogPostCardData>  $cards
     * @param  list<BlogCategoryLinkData>  $categories  menu des categories, celle de la page marquee
     * @param  string  $url  adresse canonique de cette page
     * @param  array<string, string>  $alternates  la meme page dans chaque langue
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public array $cards,
        public array $categories,
        public BlogPaginationData $pagination,
        public string $url,
        public array $alternates,
    ) {}
}
