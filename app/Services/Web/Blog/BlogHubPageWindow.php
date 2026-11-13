<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

/**
 * Decoupage du hub en pages. La premiere page montre un article de plus que les autres : le plus
 * recent y est affiche "a la une", au-dessus d'une grille complete. Calcul pur.
 */
final readonly class BlogHubPageWindow
{
    public function __construct(private int $perPage = 12) {}

    public function firstPageSize(): int
    {
        return $this->perPage + 1;
    }

    public function lastPage(int $total): int
    {
        if ($total <= $this->firstPageSize()) {
            return 1;
        }

        return 1 + (int) ceil(($total - $this->firstPageSize()) / $this->perPage);
    }

    /** Nombre d'articles a sauter pour atteindre la page demandee. */
    public function offset(int $page): int
    {
        return $page <= 1 ? 0 : $this->firstPageSize() + ($page - 2) * $this->perPage;
    }

    public function limit(int $page): int
    {
        return $page <= 1 ? $this->firstPageSize() : $this->perPage;
    }
}
