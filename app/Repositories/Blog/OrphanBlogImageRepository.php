<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogImage;
use Carbon\CarbonImmutable;

/**
 * Lecture au service de la purge des images : images sans article depuis assez longtemps, et liste
 * des dossiers connus de la base (pour reperer ceux qui n'ont plus de ligne).
 */
final readonly class OrphanBlogImageRepository
{
    /**
     * Images sans article qui n'ont pas bouge depuis `$before`. `updated_at` et non `created_at` :
     * une image detachee d'un article beneficie du delai de grace a partir de son detachement.
     *
     * @return list<int>
     */
    public function orphanIdsUntouchedSince(CarbonImmutable $before, int $limit): array
    {
        return BlogImage::query()
            ->whereNull('blog_post_id')
            ->where('updated_at', '<', $before)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    /**
     * @return list<string> uuid de toutes les images enregistrees
     */
    public function knownUuids(): array
    {
        return BlogImage::query()->pluck('uuid')->all();
    }

    /**
     * Les images rattachees a un article, avec ce qu'il faut pour retrouver leurs fichiers (audit du 23/11 : une
     * image affichee dont les fichiers ont disparu doit etre signalee). Lues par lots, jamais toutes en memoire.
     *
     * @return iterable<BlogImage>
     */
    public function attached(): iterable
    {
        return BlogImage::query()->whereNotNull('blog_post_id')->select(['id', 'blog_post_id', 'uuid', 'basename', 'variants'])->lazyById(200);
    }
}
