<?php

declare(strict_types=1);

namespace App\Actions\Blog\Image;

use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Services\Blog\Content\BlogPostHtmlRefresher;
use App\Services\Blog\Image\BlogImageTextCleaner;
use Illuminate\Support\Facades\DB;

/**
 * Modifie les descriptions d'une image depuis le formulaire d'un article, sans toucher a son credit
 * (UpdateBlogImageAction remplace les deux a la fois). L'image doit etre utilisable par cet article :
 * en attente, ou deja rattachee a lui. La description est cuite dans le HTML enregistre de l'article :
 * quand l'image est rattachee, ce HTML est regenere dans la meme transaction.
 */
final readonly class UpdateBlogImageAltAction
{
    public function __construct(
        private BlogImageTextCleaner $cleaner,
        private BlogPostHtmlRefresher $refresher,
    ) {}

    /**
     * @param  array<string, mixed>  $alt  description par langue
     * @param  int|null  $postId  article dont le formulaire fait la demande (null : article pas encore cree)
     * @return array<string, string> descriptions enregistrees, par langue (les langues vides sont absentes)
     *
     * @throws BlogImageException
     */
    public function execute(int $imageId, array $alt, ?int $postId): array
    {
        return DB::transaction(function () use ($imageId, $alt, $postId): array {
            $image = BlogImage::query()->lockForUpdate()->find($imageId)
                ?? throw BlogImageException::notFound($imageId);

            if ($image->blog_post_id !== null && $image->blog_post_id !== $postId) {
                throw BlogImageException::notUsableByPost($imageId);
            }

            $image->update(['alt' => $this->cleaner->alt($alt)]);

            if ($image->blog_post_id !== null) {
                $post = BlogPost::query()->with(['translations', 'images'])->find($image->blog_post_id);

                if ($post !== null) {
                    $this->refresher->refresh($post);
                }
            }

            return $image->alt ?? [];
        });
    }
}
