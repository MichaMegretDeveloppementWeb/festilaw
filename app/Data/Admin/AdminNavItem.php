<?php

declare(strict_types=1);

namespace App\Data\Admin;

/**
 * Un lien du menu du back-office.
 */
final readonly class AdminNavItem
{
    /**
     * @param  string  $icon  nom de l'icone Heroicons (contour)
     */
    public function __construct(
        public string $label,
        public string $url,
        public string $icon,
        public bool $active,
    ) {}
}
