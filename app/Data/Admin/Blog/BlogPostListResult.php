<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Resultat de la liste des articles du back-office : la page de lignes et les categories du filtre.
 */
final readonly class BlogPostListResult
{
    /**
     * @param  LengthAwarePaginator<int, BlogPostListRow>  $posts
     * @param  array<int, string>  $categoryOptions  identifiant => nom en francais
     */
    public function __construct(
        public LengthAwarePaginator $posts,
        public array $categoryOptions,
    ) {}
}
