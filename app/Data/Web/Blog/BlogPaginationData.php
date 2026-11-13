<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

/**
 * Pagination d'une liste d'articles. La premiere page n'a pas de parametre dans son adresse.
 */
final readonly class BlogPaginationData
{
    public function __construct(
        public string $baseUrl,
        public int $currentPage,
        public int $lastPage,
    ) {}

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function url(int $page): string
    {
        return $page <= 1 ? $this->baseUrl : $this->baseUrl.'?page='.$page;
    }

    public function previousUrl(): ?string
    {
        return $this->currentPage > 1 ? $this->url($this->currentPage - 1) : null;
    }

    public function nextUrl(): ?string
    {
        return $this->currentPage < $this->lastPage ? $this->url($this->currentPage + 1) : null;
    }

    /**
     * @return list<int>
     */
    public function pages(): array
    {
        return range(1, $this->lastPage);
    }
}
