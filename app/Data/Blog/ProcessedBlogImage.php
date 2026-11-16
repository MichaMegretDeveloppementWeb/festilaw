<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Resultat du traitement d'une image, pret a etre ecrit : une variante WebP par largeur, le JPEG de partage
 * quand l'usage le demande, et la source non decoupee quand l'image doit pouvoir etre recadree plus tard.
 */
final readonly class ProcessedBlogImage
{
    /**
     * @param  int  $width  largeur de la plus grande variante
     * @param  int  $height  hauteur de la plus grande variante
     * @param  array<int, string>  $variants  contenu WebP par largeur, largeurs croissantes
     * @param  string|null  $share  contenu du JPEG de partage, ou null
     * @param  string|null  $source  contenu WebP de la source non decoupee, ou null si elle n'est pas gardee
     * @param  BlogImageCrop|null  $crop  rectangle garde, en pixels de cette source (null sans source)
     */
    public function __construct(
        public int $width,
        public int $height,
        public array $variants,
        public ?string $share,
        public ?string $source = null,
        public ?BlogImageCrop $crop = null,
    ) {}

    /** @return list<int> */
    public function widths(): array
    {
        return array_keys($this->variants);
    }
}
