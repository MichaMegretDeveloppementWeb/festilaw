<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

/**
 * Textes d'une categorie, par langue, pour remplir son formulaire.
 */
final readonly class BlogCategoryFormState
{
    /**
     * @param  array<string, array{name: string, slug: string, description: string, metaTitle: string, metaDescription: string}>  $texts
     */
    public function __construct(
        public int $categoryId,
        public array $texts,
    ) {}
}
