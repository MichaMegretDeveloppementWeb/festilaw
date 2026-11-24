<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogImage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

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
     * Une image principale obtenue par une generation reste tant que cette generation est rattachee a un article
     * (celui qu'elle a produit, ou celui depuis lequel elle a ete demandee) : elle se retrouve dans « Images déjà
     * obtenues », meme remplacee (Micha, 24/11/2026). L'article supprime remet ces liens a nul, et elle part.
     *
     * @return list<int>
     */
    public function orphanIdsUntouchedSince(CarbonImmutable $before, int $limit): array
    {
        return BlogImage::query()
            ->whereNull('blog_post_id')
            ->where('updated_at', '<', $before)
            ->whereNotExists(fn (QueryBuilder $generation) => $generation->select(DB::raw(1))
                ->from('blog_generations')
                ->whereColumn('blog_generations.id', 'blog_images.generation_id')
                ->where(fn (QueryBuilder $linked) => $linked->whereNotNull('blog_generations.blog_post_id')->orWhereNotNull('blog_generations.source_blog_post_id')))
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
