<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Textes d'un article dans une langue, tels que saisis (ou produits par l'IA). Rien n'est nettoye ici.
 */
final readonly class BlogPostTranslationData
{
    /**
     * @param  string|null  $slug  slug choisi ; null ou vide = a generer depuis le titre
     * @param  mixed  $content  document de l'editeur, deja decode (tableau), ou null
     */
    public function __construct(
        public ?string $title = null,
        public ?string $slug = null,
        public ?string $excerpt = null,
        public mixed $content = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
    ) {}
}
