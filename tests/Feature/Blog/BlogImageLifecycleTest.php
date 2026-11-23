<?php

use App\Actions\Blog\Image\DeleteBlogImageAction;
use App\Actions\Blog\Image\StoreBlogImageAction;
use App\Actions\Blog\Image\UpdateBlogImageAction;
use App\Data\Blog\StoreBlogImageData;
use App\Data\Blog\UpdateBlogImageData;
use App\Enums\Blog\BlogImageUsage;
use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BlogImageFiles;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    // Les sources non decoupees vont sur le disque prive (audit du 23/11).
    Storage::fake('local');
});

afterEach(function () {
    BlogImageFiles::cleanup();
});

function storedPendingBlogImage(): BlogImage
{
    return app(StoreBlogImageAction::class)->execute(
        new StoreBlogImageData(BlogImageFiles::jpeg(900, 600), BlogImageUsage::Content),
    );
}

describe('updating an image', function () {
    it('cleans the descriptions and credit of a pending image', function () {
        $image = BlogImage::factory()->create();

        $updated = app(UpdateBlogImageAction::class)->execute($image->id, new UpdateBlogImageData(
            alt: ['en' => ' New ', 'fr' => '', 'es' => 'Nueva'],
            creditAuthor: ' Jane ',
            creditUrl: 'https://example.com/photo',
        ));

        expect($updated->alt)->toBe(['en' => 'New', 'es' => 'Nueva'])
            ->and($updated->credit_author)->toBe('Jane')
            ->and($updated->credit_url)->toBe('https://example.com/photo')
            ->and($updated->credit_license)->toBeNull();
    });

    it('regenerates the html of the post that shows the image', function () {
        $post = BlogPost::factory()->create();
        $image = BlogImage::factory()->create(['blog_post_id' => $post->id, 'alt' => ['fr' => 'Avant']]);
        $translation = BlogPostTranslation::factory()->for($post, 'post')->create([
            'locale' => 'fr',
            'content' => ['type' => 'doc', 'content' => [['type' => 'blogImage', 'attrs' => ['imageId' => $image->id]]]],
            'content_html' => '<figure>avant</figure>',
        ]);

        app(UpdateBlogImageAction::class)->execute($image->id, new UpdateBlogImageData(alt: ['fr' => 'Après "modification"']));

        expect($translation->fresh()->content_html)->toContain('alt="Après &quot;modification&quot;"');
    });

    it('reports an image that no longer exists', function () {
        expect(fn () => app(UpdateBlogImageAction::class)->execute(999, new UpdateBlogImageData(alt: [])))
            ->toThrow(BlogImageException::class, 'not found');
    });
});

describe('deleting an image', function () {
    it('removes the row and the files of an image that belongs to no post', function () {
        $image = storedPendingBlogImage();

        expect(Storage::disk('public')->allFiles('blog'))->toHaveCount(3)
            ->and(app(DeleteBlogImageAction::class)->execute($image->id))->toBeTrue()
            ->and(BlogImage::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles('blog'))->toBe([])
            ->and(Storage::disk('public')->directories('blog'))->toBe([]);
    });

    it('can be replayed: deleting an image already gone does nothing', function () {
        expect(app(DeleteBlogImageAction::class)->execute(12345))->toBeFalse();
    });

    it('refuses to delete an image still used by a post', function () {
        $image = storedPendingBlogImage();
        $image->update(['blog_post_id' => BlogPost::factory()->create()->id]);

        expect(fn () => app(DeleteBlogImageAction::class)->execute($image->id))
            ->toThrow(BlogImageException::class, 'attached');

        expect(BlogImage::query()->count())->toBe(1)
            ->and(Storage::disk('public')->allFiles('blog'))->toHaveCount(3);
    });

    it('removes the row and logs the folder when the files cannot be deleted', function () {
        $image = storedPendingBlogImage();
        $files = Storage::disk('public')->allFiles('blog');

        BlogImageFiles::failingDisk(failDirectoryDeletes: true);
        foreach ($files as $file) {
            Storage::disk('public')->put($file, 'kept');
        }
        Log::spy();

        expect(app(DeleteBlogImageAction::class)->execute($image->id))->toBeTrue()
            ->and(BlogImage::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles('blog'))->toHaveCount(3);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $context['directory'] === "blog/{$image->uuid}")
            ->once();
    });
});
