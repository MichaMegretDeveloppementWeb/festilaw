<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Une photo trouvee dans une banque d'images : l'adresse du fichier a telecharger, ses dimensions, et ce
 * qu'il faut garder pour le credit (page de la photo, auteur, licence).
 */
final readonly class StockImage
{
    public function __construct(
        public string $id,
        public string $downloadUrl,
        public int $width,
        public int $height,
        public string $pageUrl,
        public string $author,
        public string $license,
    ) {}
}
