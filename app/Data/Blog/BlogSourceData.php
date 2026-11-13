<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Une source citee par un article : un intitule et un lien.
 */
final readonly class BlogSourceData
{
    public function __construct(
        public string $label,
        public string $url,
    ) {}
}
