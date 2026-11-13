<?php

declare(strict_types=1);

namespace App\Services\Blog\Post;

use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Collection;

/**
 * Aligne les images rattachees a un article sur celles qu'il utilise vraiment (image principale,
 * image de partage, images citees dans le texte). Appele dans la transaction de l'Action qui
 * enregistre l'article : n'ouvre aucune transaction.
 */
final readonly class BlogPostImageSynchronizer
{
    /**
     * Rattache les images utilisees et detache les autres (elles deviennent orphelines : la purge
     * planifiee les supprimera apres le delai de grace).
     *
     * Une image utilisable est en attente (sans article) ou appartient deja a cet article. Les lignes
     * sont verrouillees : une suppression concurrente ne peut pas retirer une image qu'on rattache.
     *
     * @param  list<int>  $imageIds  identifiants utilises par l'article
     * @return Collection<int, BlogImage> les images utilisees, rattachees
     *
     * @throws BlogImageException si une image est introuvable ou appartient a un autre article
     */
    public function sync(BlogPost $post, array $imageIds): Collection
    {
        $imageIds = array_values(array_unique($imageIds));

        $images = $imageIds === []
            ? new Collection
            : BlogImage::query()->whereKey($imageIds)->lockForUpdate()->get();

        foreach ($imageIds as $imageId) {
            $image = $images->firstWhere('id', $imageId);

            if ($image === null || ($image->blog_post_id !== null && $image->blog_post_id !== $post->id)) {
                throw BlogImageException::notUsableByPost($imageId);
            }
        }

        $pending = $images->whereNull('blog_post_id');

        if ($pending->isNotEmpty()) {
            BlogImage::query()->whereKey($pending->modelKeys())->update(['blog_post_id' => $post->id, 'updated_at' => now()]);
            $pending->each(fn (BlogImage $image) => $image->setAttribute('blog_post_id', $post->id)->syncOriginal());
        }

        // `updated_at` est rafraichi : le delai de grace de la purge court a partir du detachement.
        BlogImage::query()
            ->where('blog_post_id', $post->id)
            ->when($imageIds !== [], fn ($query) => $query->whereKeyNot($imageIds))
            ->update(['blog_post_id' => null, 'updated_at' => now()]);

        return $images;
    }
}
