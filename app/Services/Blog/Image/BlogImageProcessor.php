<?php

declare(strict_types=1);

namespace App\Services\Blog\Image;

use App\Data\Blog\ProcessedBlogImage;
use App\Enums\Blog\BlogImageUsage;
use App\Exceptions\Blog\BlogImageException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Produit les fichiers d'une image du blog a partir du fichier envoye (deja controle par
 * BlogImageInspector) : orientation de l'appareil corrigee, metadonnees retirees (dont la position
 * GPS), variantes WebP sans jamais agrandir, et JPEG de partage recadre pour une image principale ou
 * de partage. L'original n'est pas conserve. Aucun acces au disque de destination.
 */
final readonly class BlogImageProcessor
{
    /**
     * @param  list<int>  $widths  largeurs cibles des variantes
     */
    public function __construct(
        private array $widths,
        private int $webpQuality,
        private int $shareWidth,
        private int $shareHeight,
        private int $shareQuality,
    ) {}

    /**
     * @throws BlogImageException
     */
    public function process(string $path, BlogImageUsage $usage): ProcessedBlogImage
    {
        try {
            $manager = ImageManager::usingDriver(Driver::class, autoOrientation: true, decodeAnimation: false, strip: true);
            $source = $manager->decodePath($path);

            // Le JPEG de partage se recadre dans l'image d'origine, avant toute reduction. Les zones
            // transparentes sont aplaties sur fond blanc par l'encodeur.
            $share = $usage->needsShareVariant()
                ? (clone $source)->cover($this->shareWidth, $this->shareHeight)
                    ->encodeUsingFormat(Format::JPEG, quality: $this->shareQuality)->toString()
                : null;

            // Reduction unique a la plus grande largeur utile ; les variantes en derivent.
            $source->scaleDown(width: max($this->widths));
            $masterWidth = $source->width();

            $variants = [];

            foreach ($this->targetWidths($masterWidth) as $width) {
                $variant = $width === $masterWidth ? $source : (clone $source)->scaleDown(width: $width);
                $variants[$width] = $variant->encodeUsingFormat(Format::WEBP, quality: $this->webpQuality)->toString();
            }

            return new ProcessedBlogImage($masterWidth, $source->height(), $variants, $share);
        } catch (Throwable $e) {
            throw BlogImageException::processingFailed($e);
        }
    }

    /**
     * Largeurs configurees strictement inferieures a celle de l'image, plus sa propre largeur : une
     * image de 1000 px donne 480, 768 et 1000, jamais 1200 ni 1600.
     *
     * @return list<int>
     */
    private function targetWidths(int $masterWidth): array
    {
        $widths = array_filter($this->widths, static fn (int $width): bool => $width < $masterWidth);
        $widths[] = $masterWidth;

        sort($widths);

        return array_values(array_unique($widths));
    }
}
