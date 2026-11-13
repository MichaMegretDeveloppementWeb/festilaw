<?php

declare(strict_types=1);

namespace App\Actions\Blog\Post;

use App\Actions\Blog\Image\DeleteBlogImageAction;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Services\Blog\Slug\BlogPostSlugAssigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Supprime un article. S'il a deja ete publie, ses adresses repondront "n'existe plus" (410) : ses
 * slugs sont gardes sans article. Ses textes et ses liens d'articles associes partent avec lui (cles
 * etrangeres). Ses images sont supprimees ensuite, apres la transaction, au mieux : celle qui
 * resisterait reste orpheline et la purge planifiee la reprendra.
 */
final readonly class DeleteBlogPostAction
{
    public function __construct(
        private BlogPostSlugAssigner $slugs,
        private DeleteBlogImageAction $deleteImage,
    ) {}

    /**
     * @return bool false si l'article n'existait deja plus (l'operation est rejouable sans effet)
     */
    public function execute(int $postId): bool
    {
        $imageIds = DB::transaction(function () use ($postId): ?array {
            $post = BlogPost::query()->with('translations')->lockForUpdate()->find($postId);

            if ($post === null) {
                return null;
            }

            $imageIds = BlogImage::query()->where('blog_post_id', $post->id)->pluck('id')->all();

            $this->slugs->entomb($post);
            $post->delete();

            return $imageIds;
        });

        if ($imageIds === null) {
            return false;
        }

        foreach ($imageIds as $imageId) {
            try {
                $this->deleteImage->execute($imageId);
            } catch (Throwable $e) {
                Log::warning('Blog image not deleted with its post; the purge will retry.', [
                    'image_id' => $imageId,
                    'post_id' => $postId,
                    'exception' => $e,
                ]);
            }
        }

        return true;
    }
}
