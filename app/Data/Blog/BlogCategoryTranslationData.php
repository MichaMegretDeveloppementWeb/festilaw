<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Textes d'une categorie dans une langue, tels que saisis.
 */
final readonly class BlogCategoryTranslationData
{
    /**
     * @param  string|null  $slug  slug choisi ; null ou vide = a generer depuis le nom
     */
    public function __construct(
        public ?string $name = null,
        public ?string $slug = null,
        public ?string $description = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
    ) {}
}
