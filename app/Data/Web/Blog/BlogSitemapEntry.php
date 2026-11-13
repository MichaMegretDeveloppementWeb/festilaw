<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

/**
 * Une page du sitemap : ses adresses dans chaque langue et, si elle est connue, sa date de derniere
 * modification.
 */
final readonly class BlogSitemapEntry
{
    /**
     * @param  array<string, string>  $urls  locale => adresse
     * @param  string|null  $lastmod  date au format W3C (ISO 8601), ou null
     */
    public function __construct(
        public array $urls,
        public ?string $lastmod = null,
    ) {}
}
