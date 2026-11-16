<?php

declare(strict_types=1);

namespace App\Actions\Blog\Image;

use App\Data\Blog\BlogImageCrop;
use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Services\Blog\Image\BlogImageProcessor;
use App\Services\Blog\Image\BlogImageStorage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recadre de nouveau une image du texte, a partir de sa source gardee. Elle ne modifie pas l'image d'origine :
 * elle en cree une NOUVELLE, en attente, avec le meme nom, les memes descriptions et le meme credit. Le
 * formulaire remplace l'ancienne par la nouvelle dans le texte ; l'ancienne reste intacte tant que l'article
 * n'est pas enregistre, puis part avec la purge des images sans article. Un article en ligne ne peut donc
 * jamais pointer vers un fichier disparu.
 *
 * Les fichiers sont ecrits AVANT la ligne, et retires si la ligne ne peut pas etre creee.
 */
final readonly class RecropBlogImageAction
{
    public function __construct(
        private BlogImageProcessor $processor,
        private BlogImageStorage $storage,
    ) {}

    /**
     * @param  BlogImageCrop  $crop  rectangle a garder, en pixels de la source gardee
     * @param  int|null  $postId  article dont le formulaire fait la demande (null : article pas encore cree)
     *
     * @throws BlogImageException
     */
    public function execute(int $imageId, BlogImageCrop $crop, ?int $postId): BlogImage
    {
        $image = BlogImage::query()->find($imageId) ?? throw BlogImageException::notFound($imageId);

        if ($image->blog_post_id !== null && $image->blog_post_id !== $postId) {
            throw BlogImageException::notUsableByPost($imageId);
        }

        if (! $image->hasSource()) {
            throw BlogImageException::notRecroppable($imageId);
        }

        $processed = $this->processor->recrop($this->storage->readSource($image), $crop);
        $uuid = (string) Str::uuid();

        $this->storage->write($uuid, $image->basename, $processed);

        try {
            return BlogImage::query()->create([
                'uuid' => $uuid,
                'basename' => $image->basename,
                'variants' => ['widths' => $processed->widths(), 'share' => false, 'source' => true],
                'width' => $processed->width,
                'height' => $processed->height,
                'crop' => $processed->crop?->toArray(),
                'alt' => $image->alt,
                'credit_source' => $image->credit_source,
                'credit_author' => $image->credit_author,
                'credit_url' => $image->credit_url,
                'credit_license' => $image->credit_license,
            ]);
        } catch (Throwable $e) {
            $this->storage->deleteQuietly($uuid);

            throw $e;
        }
    }
}
