<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Models\BlogImage;
use App\Services\Blog\Image\BlogImagePaths;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Adresse d'apercu d'une image du blog pour le back-office (vignette de la liste, apercu dans le
 * formulaire). Aucune lecture en base ni sur le disque.
 */
final readonly class BlogImagePreviewFactory
{
    public function __construct(
        private BlogImagePaths $paths,
        private UrlGenerator $url,
    ) {}

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

    /**
     * Adresse du fichier a partir duquel l'image se recadre : sa source gardee, servie par le back-office depuis
     * le disque prive (audit du 23/11), sinon sa plus grande variante publique.
     */
    public function sourceUrl(BlogImage $image): ?string
    {
        if ($image->hasSource()) {
            return $this->url->route('admin.blog.images.source', ['image' => $image->id]);
        }

        $path = $this->paths->recropSourceOf($image);

        return $path === null ? null : $this->paths->url($path);
    }
}
