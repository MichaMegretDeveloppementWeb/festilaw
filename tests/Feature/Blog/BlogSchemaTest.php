<?php

use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\BlogPostRetiredSlug;
use App\Models\BlogPostTranslation;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a ready post with its category, featured image and three written translations', function () {
    $post = BlogPost::factory()->ready()->create()->load(['category.translations', 'translations', 'featuredImage', 'images']);

    expect($post->status)->toBe(BlogPostStatus::Draft)
        ->and($post->origin)->toBe(BlogPostOrigin::Manual)
        ->and($post->category)->toBeInstanceOf(BlogCategory::class)
        ->and($post->category->translations)->toHaveCount(3)
        ->and($post->translations)->toHaveCount(3)
        ->and($post->translations->every->isWritten())->toBeTrue()
        ->and($post->featuredImage)->toBeInstanceOf(BlogImage::class)
        ->and($post->featuredImage->hasShareVariant())->toBeTrue()
        ->and($post->images)->toHaveCount(1)
        ->and($post->translationFor('fr')->locale)->toBe('fr');
});

it('applies the column defaults and casts dates to immutable instances', function () {
    $post = BlogPost::factory()->published()->create()->fresh();
    $image = BlogImage::factory()->create()->fresh();

    expect($post->published_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($post->hasBeenPublished())->toBeTrue()
        ->and(BlogPost::factory()->create()->fresh()->hasBeenPublished())->toBeFalse()
        ->and($image->credit_source)->toBe(BlogImageCreditSource::Upload)
        ->and($image->widths())->toBe([480, 768, 1200, 1600])
        ->and($image->altFor('fr'))->toBe('Une image d\'exemple')
        ->and($image->altFor('de'))->toBe('');
});

it('keeps a slug unique within a locale but lets several translations stay without a slug', function () {
    BlogPostTranslation::factory()->create(['locale' => 'fr', 'slug' => 'mon-article']);

    BlogPostTranslation::factory()->create(['locale' => 'es', 'slug' => 'mon-article']);
    BlogPostTranslation::factory()->blank()->create(['locale' => 'fr']);
    BlogPostTranslation::factory()->blank()->create(['locale' => 'fr']);

    expect(fn () => BlogPostTranslation::factory()->create(['locale' => 'fr', 'slug' => 'mon-article']))
        ->toThrow(QueryException::class);
});

it('refuses to delete a category that still holds posts', function () {
    $post = BlogPost::factory()->ready()->create();

    expect(fn () => $post->category->delete())->toThrow(QueryException::class);
});

it('removes the translations with the post and leaves its images and old slugs without an owner', function () {
    $post = BlogPost::factory()->ready()->create();
    $imageId = $post->featured_image_id;
    $retired = BlogPostRetiredSlug::factory()->create(['blog_post_id' => $post->id]);

    $post->delete();

    expect(BlogPostTranslation::query()->where('blog_post_id', $post->id)->count())->toBe(0)
        ->and(BlogImage::query()->find($imageId)->blog_post_id)->toBeNull()
        ->and($retired->fresh()->blog_post_id)->toBeNull();
});

it('orders related posts by the chosen position', function () {
    $post = BlogPost::factory()->create();
    [$first, $second] = BlogPost::factory()->count(2)->create();

    $post->relatedPosts()->attach([$second->id => ['position' => 0], $first->id => ['position' => 1]]);

    expect($post->relatedPosts()->pluck('blog_posts.id')->all())->toBe([$second->id, $first->id]);
});
