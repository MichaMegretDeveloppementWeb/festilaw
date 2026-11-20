<?php

declare(strict_types=1);

namespace App\Data\Admin;

/**
 * Un lien du menu du back-office : une ligne a lui, ou une page d'une section.
 */
final readonly class AdminNavItem
{
    /**
     * @param  string|null  $icon  nom de l'icone Heroicons (contour) ; aucune pour une page de section
     */
    public function __construct(
        public string $label,
        public string $url,
        public ?string $icon,
        public bool $active,
    ) {}
}
