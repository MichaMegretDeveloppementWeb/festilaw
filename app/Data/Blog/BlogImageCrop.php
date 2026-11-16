<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Le rectangle garde d'une image, en pixels de l'image a laquelle il s'applique (redressee) : son coin haut
 * gauche, sa largeur et sa hauteur. Calcul pur.
 */
final readonly class BlogImageCrop
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}

    /** Ce que le navigateur envoie, ou ce qui est enregistre. Nul si ce n'est pas un rectangle. */
    public static function fromArray(mixed $value): ?self
    {
        if (! is_array($value)) {
            return null;
        }

        foreach (['x', 'y', 'width', 'height'] as $key) {
            if (! is_numeric($value[$key] ?? null)) {
                return null;
            }
        }

        $crop = new self((int) round((float) $value['x']), (int) round((float) $value['y']), (int) round((float) $value['width']), (int) round((float) $value['height']));

        return $crop->width > 0 && $crop->height > 0 ? $crop : null;
    }

    /** L'image entiere. */
    public static function whole(int $imageWidth, int $imageHeight): self
    {
        return new self(0, 0, $imageWidth, $imageHeight);
    }

    /**
     * Le meme rectangle, ramene dans les limites de l'image : un arrondi du navigateur peut le faire
     * depasser d'un pixel. Nul s'il ne recouvre plus rien.
     */
    public function clampedTo(int $imageWidth, int $imageHeight): ?self
    {
        $x = max(0, min($this->x, $imageWidth));
        $y = max(0, min($this->y, $imageHeight));
        $width = min($this->x + $this->width, $imageWidth) - $x;
        $height = min($this->y + $this->height, $imageHeight) - $y;

        return $width > 0 && $height > 0 ? new self($x, $y, $width, $height) : null;
    }

    /** Le meme rectangle sur une copie de l'image reduite (ou agrandie) dans ce rapport. */
    public function scaled(float $ratio): self
    {
        return new self(
            (int) round($this->x * $ratio),
            (int) round($this->y * $ratio),
            max(1, (int) round($this->width * $ratio)),
            max(1, (int) round($this->height * $ratio)),
        );
    }

    /** @return array{x: int, y: int, width: int, height: int} */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'width' => $this->width, 'height' => $this->height];
    }
}
