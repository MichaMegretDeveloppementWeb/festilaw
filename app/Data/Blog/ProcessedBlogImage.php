<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Resultat du traitement d'une image, pret a etre ecrit : une variante WebP par largeur, et le JPEG
 * de partage quand l'usage le demande.
 */
final readonly class ProcessedBlogImage
{
    /**
     * @param  int  $width  largeur de la plus grande variante
     * @param  int  $height  hauteur de la plus grande variante
     * @param  array<int, string>  $variants  contenu WebP par largeur, largeurs croissantes
     * @param  string|null  $share  contenu du JPEG de partage, ou null
     */
    public function __construct(
        public int $width,
        public int $height,
        public array $variants,
        public ?string $share,
    ) {}

    /** @return list<int> */
    public function widths(): array
    {
        return array_keys($this->variants);
    }
}
