<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

/**
 * Pagination d'une liste d'articles. La premiere page n'a pas de parametre dans son adresse.
 */
final readonly class BlogPaginationData
{
    /** Pages montrees de part et d'autre de la page courante, en plus de la premiere et de la derniere. */
    private const WINDOW = 2;

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
     * Les numeros a montrer : la premiere page, la derniere, et deux de part et d'autre de la page courante (audit
     * du 23/11 : avec un article par jour, la liste aurait compte des dizaines de numeros). Null marque un saut ;
     * un saut d'une seule page montre la page plutot que des points de suspension.
     *
     * @return list<int|null>
     */
    public function pages(): array
    {
        $shown = array_unique([1, $this->lastPage, ...range(max(1, $this->currentPage - self::WINDOW), min($this->lastPage, $this->currentPage + self::WINDOW))]);
        sort($shown);
        $pages = [];
        $previous = 0;

        foreach ($shown as $page) {
            if ($page - $previous === 2) {
                $pages[] = $previous + 1;
            } elseif ($page - $previous > 2) {
                $pages[] = null;
            }

            $pages[] = $page;
            $previous = $page;
        }

        return $pages;
    }
}
