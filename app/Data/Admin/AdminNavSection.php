<?php

declare(strict_types=1);

namespace App\Data\Admin;

/**
 * Une section repliable du menu du back-office (Blog, Audience, Marketing), dessinee comme sur
 * Chouchoute-toi (demande de Micha du 20/11) : sa ligne porte l'icone, ses pages n'en ont pas, et barre
 * repliee, son nom mene a sa premiere page.
 */
final readonly class AdminNavSection
{
    /**
     * @param  string  $name  cle sous laquelle le kit retient la section ouverte ou fermee
     * @param  string  $icon  nom de l'icone Heroicons (contour)
     * @param  string  $url  adresse de la premiere page de la section
     * @param  bool  $active  une page de la section est ouverte
     * @param  list<AdminNavItem>  $items  les pages de la section, sans icone
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $icon,
        public string $url,
        public bool $active,
        public array $items,
    ) {}
}
