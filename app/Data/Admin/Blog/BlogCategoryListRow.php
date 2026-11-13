<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

/**
 * Une ligne de l'ecran des categories du back-office.
 */
final readonly class BlogCategoryListRow
{
    /**
     * @param  array<string, string>  $names  nom par langue
     * @param  array<string, string>  $slugs  adresse par langue
     * @param  int  $postCount  articles rattaches, brouillons compris
     */
    public function __construct(
        public int $id,
        public array $names,
        public array $slugs,
        public int $postCount,
    ) {}
}
