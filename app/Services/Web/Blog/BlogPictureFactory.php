<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogPictureData;
use App\Models\BlogImage;
use App\Services\Blog\Image\BlogImagePaths;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Transforme une image du blog en donnees pretes a afficher (adresses des variantes, dimensions,
 * description dans la langue). Aucune lecture en base ni sur le disque.
 */
final readonly class BlogPictureFactory
{
    /** Largeur de la variante servie par defaut (attribut src) quand elle existe. */
    private const DEFAULT_WIDTH = 1200;

    public function __construct(
        private BlogImagePaths $paths,
        private UrlGenerator $url,
    ) {}

    /** Null pour une image sans variante exploitable. */
    public function make(BlogImage $image, string $locale): ?BlogPictureData
    {
        $widths = $image->widths();

        if ($widths === []) {
            return null;
        }

        $default = in_array(self::DEFAULT_WIDTH, $widths, true) ? self::DEFAULT_WIDTH : max($widths);

        return new BlogPictureData(
            src: $this->paths->url($this->paths->variantOf($image, $default)),
            srcset: implode(', ', array_map(
                fn (int $width): string => $this->paths->url($this->paths->variantOf($image, $width))." {$width}w",
                $widths,
            )),
            width: $image->width,
            height: $image->height,
            alt: $image->altFor($locale),
            // Les reseaux sociaux exigent une adresse complete, avec le domaine.
            shareUrl: $image->hasShareVariant() ? $this->url->to($this->paths->url($this->paths->shareOf($image))) : null,
        );
    }
}
