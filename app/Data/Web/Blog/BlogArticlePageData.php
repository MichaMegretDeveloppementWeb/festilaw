<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

use App\Data\Blog\BlogAuthorData;
use Carbon\CarbonImmutable;

/**
 * Tout ce que la page d'un article affiche et declare (balises, donnees enrichies), dans une langue.
 */
final readonly class BlogArticlePageData
{
    /**
     * @param  string  $html  contenu de l'article, deja rendu et echappe par le serveur a l'enregistrement
     * @param  string  $url  adresse canonique de l'article dans cette langue
     * @param  array<string, string>  $alternates  l'article dans chaque langue (slug propre a chacune)
     * @param  CarbonImmutable|null  $updatedAt  derniere modification de fond, si elle existe
     * @param  string|null  $shareImageUrl  adresse ABSOLUE de l'image pour les reseaux sociaux
     * @param  list<array{label: string, url: string}>  $sources
     * @param  list<BlogPostCardData>  $related
     */
    public function __construct(
        public string $title,
        public ?string $excerpt,
        public string $html,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public string $url,
        public array $alternates,
        public CarbonImmutable $publishedAt,
        public ?CarbonImmutable $updatedAt,
        public ?BlogCategoryLinkData $category,
        public ?BlogPictureData $picture,
        public ?string $shareImageUrl,
        public ?string $shareImageAlt,
        public array $sources,
        public array $related,
        public BlogAuthorData $author,
        public ?string $transparencyNotice = null,
    ) {}

    public function publishedLabel(string $locale): string
    {
        return $this->publishedAt->locale($locale)->isoFormat('LL');
    }

    public function updatedLabel(string $locale): ?string
    {
        return $this->updatedAt?->locale($locale)->isoFormat('LL');
    }
}
