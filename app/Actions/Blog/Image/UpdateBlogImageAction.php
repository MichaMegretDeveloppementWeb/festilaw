<?php

declare(strict_types=1);

namespace App\Actions\Blog\Image;

use App\Data\Blog\UpdateBlogImageData;
use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Services\Blog\Content\BlogPostHtmlRefresher;
use App\Services\Blog\Image\BlogImageTextCleaner;
use Illuminate\Support\Facades\DB;

/**
 * Modifie les descriptions et le credit d'une image. La description est cuite dans le HTML enregistre
 * de l'article : quand l'image est rattachee, ce HTML est regenere dans la meme transaction.
 */
final readonly class UpdateBlogImageAction
{
    public function __construct(
        private BlogImageTextCleaner $cleaner,
        private BlogPostHtmlRefresher $refresher,
    ) {}

    /**
     * @throws BlogImageException
     */
    public function execute(int $imageId, UpdateBlogImageData $data): BlogImage
    {
        return DB::transaction(function () use ($imageId, $data): BlogImage {
            $image = BlogImage::query()->lockForUpdate()->find($imageId)
                ?? throw BlogImageException::notFound($imageId);

            $image->update([
                'alt' => $this->cleaner->alt($data->alt),
                'credit_author' => $this->cleaner->credit($data->creditAuthor),
                'credit_url' => $this->cleaner->creditUrl($data->creditUrl),
                'credit_license' => $this->cleaner->credit($data->creditLicense),
            ]);

            if ($image->blog_post_id !== null) {
                $post = BlogPost::query()->with(['translations', 'images'])->find($image->blog_post_id);

                if ($post !== null) {
                    $this->refresher->refresh($post);
                }
            }

            return $image;
        });
    }
}
