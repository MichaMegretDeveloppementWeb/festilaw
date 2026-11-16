<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Resultat de la liste des articles du back-office : la page de lignes, les categories du filtre et le nombre
 * d'articles par statut (bande de chiffres qui sert de filtre par statut).
 */
final readonly class BlogPostListResult
{
    /**
     * @param  LengthAwarePaginator<int, BlogPostListRow>  $posts
     * @param  array<int, string>  $categoryOptions  identifiant => nom en francais
     * @param  array<string, int>  $statusCounts  valeur de BlogPostStatus => nombre d'articles (0 compris)
     */
    public function __construct(
        public LengthAwarePaginator $posts,
        public array $categoryOptions,
        public array $statusCounts = [],
    ) {}

    /** Nombre d'articles, tous statuts confondus, avec les autres filtres. */
    public function totalCount(): int
    {
        return array_sum($this->statusCounts);
    }
}
