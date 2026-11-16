<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Models\BlogImage;
use App\Services\Blog\Image\BlogImagePaths;

/**
 * Adresse d'apercu d'une image du blog pour le back-office (vignette de la liste, apercu dans le
 * formulaire). Aucune lecture en base ni sur le disque.
 */
final readonly class BlogImagePreviewFactory
{
    public function __construct(private BlogImagePaths $paths) {}

    /**
     * Adresse de la plus petite variante au moins aussi large que demande (a defaut la plus grande).
     * Null pour une image sans variante.
     */
    public function url(BlogImage $image, int $minWidth = 480): ?string
    {
        $widths = $image->widths();

        if ($widths === []) {
            return null;
        }

        $suitable = array_values(array_filter($widths, static fn (int $width): bool => $width >= $minWidth));

        return $this->paths->url($this->paths->variantOf($image, $suitable === [] ? max($widths) : min($suitable)));
    }

    /** Adresse de la source non decoupee d'une image recadrable, ou null si elle n'a pas ete gardee. */
    public function sourceUrl(BlogImage $image): ?string
    {
        return $image->hasSource() ? $this->paths->url($this->paths->sourceOf($image)) : null;
    }
}
