<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

use App\Enums\Blog\BlogPostListSort;

/**
 * Filtres et tri de la liste des articles du back-office. Une chaine vide = pas de filtre.
 */
final readonly class BlogPostListFilters
{
    /**
     * @param  string  $search  mots du titre, dans l'une des trois langues
     * @param  string  $status  valeur de BlogPostStatus
     * @param  string  $origin  valeur de BlogPostOrigin
     */
    public function __construct(
        public string $search = '',
        public string $status = '',
        public ?int $categoryId = null,
        public string $origin = '',
        public BlogPostListSort $sort = BlogPostListSort::Recent,
        public bool $descending = true,
    ) {}
}
