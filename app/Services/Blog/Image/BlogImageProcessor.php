<?php

declare(strict_types=1);

namespace App\Services\Blog\Image;

use App\Data\Blog\BlogImageCrop;
use App\Data\Blog\ProcessedBlogImage;
use App\Enums\Blog\BlogImageUsage;
use App\Exceptions\Blog\BlogImageException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Produit les fichiers d'une image du blog a partir du fichier envoye (deja controle par
 * BlogImageInspector) : orientation de l'appareil corrigee, metadonnees retirees (dont la position
 * GPS), decoupe eventuelle, variantes WebP sans jamais agrandir, et JPEG de partage recadre pour une image
 * principale ou de partage. Aucun acces au disque de destination.
 *
 * Le fichier envoye n'est pas conserve tel quel. Quand l'image doit pouvoir etre recadree plus tard, sa
 * source NON decoupee est gardee en WebP, reduite a `sourceWidth` : un nouveau cadrage se refait a partir
 * d'elle, sans renvoyer le fichier.
 */
final readonly class BlogImageProcessor
{
    /**
     * @param  list<int>  $widths  largeurs cibles des variantes
     * @param  int  $sourceWidth  largeur maximale de la source gardee pour un recadrage ulterieur
     * @param  int  $minCropWidth  largeur minimale de la partie gardee, en pixels de l'image decoupee
     */
    public function __construct(
        private array $widths,
        private int $webpQuality,
        private int $shareWidth,
        private int $shareHeight,
        private int $shareQuality,
        private int $sourceWidth = 2_400,
        private int $minCropWidth = 200,
        private int $minCropHeight = 120,
    ) {}

    /**
     * @param  BlogImageCrop|null  $crop  rectangle a garder, en pixels de l'image redressee (null : toute l'image)
     * @param  bool  $keepSource  garder la source non decoupee, pour un recadrage ulterieur
     *
     * @throws BlogImageException
     */
    public function process(string $path, BlogImageUsage $usage, ?BlogImageCrop $crop = null, bool $keepSource = false): ProcessedBlogImage
    {
        try {
            $image = $this->manager()->decodePath($path);
            $source = null;
            $sourceCrop = null;

            if ($keepSource) {
                $kept = (clone $image)->scaleDown(width: $this->sourceWidth);
                $source = $kept->encodeUsingFormat(Format::WEBP, quality: $this->webpQuality)->toString();
                // Le cadrage est enregistre dans les pixels de la source gardee, qui peut etre plus petite que le fichier envoye.
                $sourceCrop = ($crop ?? BlogImageCrop::whole($image->width(), $image->height()))
                    ->scaled($kept->width() / $image->width())
                    ->clampedTo($kept->width(), $kept->height());
            }

            return $this->build($image, $usage, $crop, $source, $sourceCrop);
        } catch (BlogImageException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw BlogImageException::processingFailed($e);
        }
    }

    /**
     * Nouveau cadrage d'une image du texte, a partir de sa source gardee. La source est reprise telle quelle
     * (jamais reencodee d'un recadrage a l'autre).
     *
     * @param  string  $source  contenu WebP de la source gardee
     * @param  BlogImageCrop  $crop  rectangle a garder, en pixels de cette source
     *
     * @throws BlogImageException
     */
    public function recrop(string $source, BlogImageCrop $crop): ProcessedBlogImage
    {
        try {
            $image = $this->manager()->decodeBinary($source);

            return $this->build($image, BlogImageUsage::Content, $crop, $source, $crop->clampedTo($image->width(), $image->height()));
        } catch (BlogImageException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw BlogImageException::processingFailed($e);
        }
    }

    private function build(ImageInterface $image, BlogImageUsage $usage, ?BlogImageCrop $crop, ?string $source, ?BlogImageCrop $sourceCrop): ProcessedBlogImage
    {
        if ($crop !== null) {
            $kept = $crop->clampedTo($image->width(), $image->height());

            if ($kept === null || $kept->width < $this->minCropWidth || $kept->height < $this->minCropHeight) {
                throw BlogImageException::cropTooSmall($this->minCropWidth, $this->minCropHeight);
            }

            $image->crop($kept->width, $kept->height, $kept->x, $kept->y);
        }

        // Le JPEG de partage se recadre dans l'image d'origine, avant toute reduction. Les zones
        // transparentes sont aplaties sur fond blanc par l'encodeur.
        $share = $usage->needsShareVariant()
            ? (clone $image)->cover($this->shareWidth, $this->shareHeight)
                ->encodeUsingFormat(Format::JPEG, quality: $this->shareQuality)->toString()
            : null;

        // Reduction unique a la plus grande largeur utile ; les variantes en derivent.
        $image->scaleDown(width: max($this->widths));
        $masterWidth = $image->width();

        $variants = [];

        foreach ($this->targetWidths($masterWidth) as $width) {
            $variant = $width === $masterWidth ? $image : (clone $image)->scaleDown(width: $width);
            $variants[$width] = $variant->encodeUsingFormat(Format::WEBP, quality: $this->webpQuality)->toString();
        }

        return new ProcessedBlogImage($masterWidth, $image->height(), $variants, $share, $source, $source === null ? null : $sourceCrop);
    }

    private function manager(): ImageManager
    {
        return ImageManager::usingDriver(Driver::class, autoOrientation: true, decodeAnimation: false, strip: true);
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
