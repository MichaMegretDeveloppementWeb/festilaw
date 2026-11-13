<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

use Carbon\CarbonImmutable;

/**
 * Carte d'article telle qu'elle s'affiche sur le hub, une page de categorie, l'accueil ou sous un
 * article (articles associes).
 */
final readonly class BlogPostCardData
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $excerpt,
        public CarbonImmutable $publishedAt,
        public ?BlogCategoryLinkData $category,
        public ?BlogPictureData $picture,
    ) {}

    /** Date de publication ecrite dans la langue demandee ("12 novembre 2026"). */
    public function publishedLabel(string $locale): string
    {
        return $this->publishedAt->locale($locale)->isoFormat('LL');
    }
}
