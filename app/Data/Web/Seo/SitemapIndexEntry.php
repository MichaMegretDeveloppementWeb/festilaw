<?php

declare(strict_types=1);

namespace App\Data\Web\Seo;

/**
 * Un fichier cite par l'index du sitemap : son adresse et, si elle est connue, la date de la derniere
 * modification de ce qu'il liste.
 */
final readonly class SitemapIndexEntry
{
    /**
     * @param  string|null  $lastmod  date au format W3C (ISO 8601), ou null
     */
    public function __construct(
        public string $url,
        public ?string $lastmod = null,
    ) {}
}
