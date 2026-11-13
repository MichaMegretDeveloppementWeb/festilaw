<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

/**
 * Image du blog prete a afficher : adresses des variantes, dimensions et description dans la langue de
 * la page. L'attribut `sizes` depend de l'endroit ou l'image est placee : c'est la vue qui le fixe.
 */
final readonly class BlogPictureData
{
    /**
     * @param  string  $src  adresse de la variante servie par defaut
     * @param  string  $srcset  toutes les variantes, avec leur largeur
     * @param  string|null  $shareUrl  adresse ABSOLUE du JPEG 1200 x 630 (reseaux sociaux), s'il existe
     */
    public function __construct(
        public string $src,
        public string $srcset,
        public int $width,
        public int $height,
        public string $alt,
        public ?string $shareUrl,
    ) {}
}
