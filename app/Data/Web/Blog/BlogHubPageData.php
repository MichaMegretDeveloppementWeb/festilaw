<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

/**
 * Tout ce que la page d'accueil du blog affiche, dans une langue.
 */
final readonly class BlogHubPageData
{
    /**
     * @param  BlogPostCardData|null  $featured  l'article le plus recent, "a la une" (premiere page seulement)
     * @param  list<BlogPostCardData>  $cards  les articles de la grille
     * @param  list<BlogCategoryLinkData>  $categories  categories ayant au moins un article publie
     * @param  string  $url  adresse canonique de cette page
     * @param  array<string, string>  $alternates  la meme page dans chaque langue
     */
    public function __construct(
        public ?BlogPostCardData $featured,
        public array $cards,
        public array $categories,
        public BlogPaginationData $pagination,
        public string $url,
        public array $alternates,
    ) {}

    /** Aucun article publie : la page affiche un message d'attente et n'est pas indexee. */
    public function isEmpty(): bool
    {
        return $this->featured === null && $this->cards === [];
    }
}
