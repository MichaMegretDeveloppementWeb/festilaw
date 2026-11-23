<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Services\Blog\Image\BlogImageStorage;

/**
 * Le fichier a partir duquel une image du blog se recadre, pour l'outil de recadrage du back-office : sa
 * source non decoupee, rangee sur le disque prive (audit du 23/11), sinon sa plus grande taille. Une requete.
 */
final readonly class BlogImageSourceService
{
    public function __construct(private BlogImageStorage $storage) {}

    /**
     * Null quand l'image n'existe plus.
     *
     * @throws BlogImageException quand l'image n'a aucun fichier a recadrer ou que le disque refuse la lecture
     */
    public function contents(int $imageId): ?string
    {
        $image = BlogImage::query()->find($imageId);

        return $image === null ? null : $this->storage->readSource($image);
    }
}
