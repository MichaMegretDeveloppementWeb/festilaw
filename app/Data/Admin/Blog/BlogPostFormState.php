<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

/**
 * Valeurs de depart des champs du formulaire d'un article existant (ce que le composant garde en etat).
 * Le contenu des trois langues n'en fait pas partie : il vit dans le navigateur.
 */
final readonly class BlogPostFormState
{
    /**
     * @param  array<string, array{title: string, slug: string, excerpt: string, metaTitle: string, metaDescription: string}>  $texts  par langue
     * @param  list<int>  $relatedIds  articles lies, dans leur ordre
     * @param  list<array{label: string, url: string}>  $sources
     * @param  string  $scheduledFor  date de programmation (UTC) au format du champ, ou chaine vide
     */
    public function __construct(
        public int $postId,
        public array $texts,
        public ?int $categoryId,
        public ?int $featuredImageId,
        public ?int $shareImageId,
        public array $relatedIds,
        public array $sources,
        public string $scheduledFor,
    ) {}
}
