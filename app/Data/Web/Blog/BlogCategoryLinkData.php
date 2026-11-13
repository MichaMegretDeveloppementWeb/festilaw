<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

/**
 * Lien vers la page d'une categorie, dans la langue de la page.
 */
final readonly class BlogCategoryLinkData
{
    public function __construct(
        public string $name,
        public string $url,
        public bool $isCurrent = false,
    ) {}
}
