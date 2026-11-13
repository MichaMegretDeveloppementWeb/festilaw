<?php

declare(strict_types=1);

namespace App\Actions\Blog\Image;

use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Services\Blog\Image\BlogImageStorage;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une image qui n'appartient a aucun article (en attente ou orpheline). La ligne fait foi :
 * elle est supprimee d'abord, sous verrou, pour qu'un enregistrement d'article en cours ne puisse pas
 * rattacher une image dont les fichiers disparaissent. Les fichiers sont retires ensuite, au mieux ;
 * s'ils resistent, c'est journalise et la purge planifiee balaie les dossiers sans ligne.
 */
final readonly class DeleteBlogImageAction
{
    public function __construct(private BlogImageStorage $storage) {}

    /**
     * @return bool false si l'image n'existait deja plus (l'operation est rejouable sans effet)
     *
     * @throws BlogImageException si l'image est encore rattachee a un article
     */
    public function execute(int $imageId): bool
    {
        $uuid = DB::transaction(function () use ($imageId): ?string {
            $image = BlogImage::query()->lockForUpdate()->find($imageId);

            if ($image === null) {
                return null;
            }

            if ($image->blog_post_id !== null) {
                throw BlogImageException::stillAttached($imageId);
            }

            $image->delete();

            return $image->uuid;
        });

        if ($uuid === null) {
            return false;
        }

        $this->storage->deleteQuietly($uuid);

        return true;
    }
}
