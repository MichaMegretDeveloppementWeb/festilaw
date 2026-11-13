<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Ce que l'inspection d'un fichier image a etabli avant tout traitement : dimensions telles qu'elles
 * seront affichees (orientation de l'appareil prise en compte) et type detecte sur le contenu.
 */
final readonly class BlogImageMetadata
{
    public function __construct(
        public int $width,
        public int $height,
        public string $mimeType,
    ) {}
}
