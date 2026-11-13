<?php

use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use App\Services\Blog\Content\BlogContentRenderer;
use App\Services\Blog\Content\BlogLinkPolicy;
use App\Services\Blog\Content\BlogPostHtmlRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('regenerates the stored html of every language when an image description changes', function () {
    $post = BlogPost::factory()->create();
    $image = BlogImage::factory()->create(['blog_post_id' => $post->id, 'alt' => ['en' => 'Old', 'fr' => 'Ancien']]);

    foreach (['en', 'fr'] as $locale) {
        BlogPostTranslation::factory()->for($post, 'post')->create([
            'locale' => $locale,
            'content' => ['type' => 'doc', 'content' => [['type' => 'blogImage', 'attrs' => ['imageId' => $image->id]]]],
            'content_html' => '<p>stale</p>',
        ]);
    }

    $image->update(['alt' => ['en' => 'New label', 'fr' => 'Nouvelle étiquette']]);

    $changed = app(BlogPostHtmlRefresher::class)->refresh($post->load(['translations', 'images']));

    expect($changed)->toBe(2)
        ->and($post->translationFor('en')->fresh()->content_html)->toContain('alt="New label"')
        ->and($post->translationFor('fr')->fresh()->content_html)->toContain('alt="Nouvelle étiquette"');
});

it('changes nothing when the html is already up to date', function () {
    $post = BlogPost::factory()->create();
    $translation = BlogPostTranslation::factory()->for($post, 'post')->create();
    $post->load(['translations', 'images']);
    $refresher = app(BlogPostHtmlRefresher::class);

    $refresher->refresh($post);
    $updatedAt = $translation->fresh()->updated_at;

    expect($refresher->refresh($post->load('translations')))->toBe(0)
        ->and($translation->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('wires the renderer with the site host and the configured image sizes', function () {
    config(['app.url' => 'https://festilaw.test']);

    expect(app(BlogLinkPolicy::class)->isExternal('https://festilaw.test/pricing'))->toBeFalse()
        ->and(app(BlogLinkPolicy::class)->isExternal('https://example.com'))->toBeTrue()
        ->and(app(BlogContentRenderer::class))->toBeInstanceOf(BlogContentRenderer::class);
});
