<?php

use App\Enums\Blog\Generation\BlogGenerationType;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Models\BlogImage;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Rattrapage du 24/11/2026 : les demandes deja faites et leurs images sont rattachees a leur article, pour que
 | l'image payee en production depuis un brouillon ne parte pas a la purge.
 */

uses(RefreshDatabase::class);

function runRequestLinksBackfill(): void
{
    (require database_path('migrations/2026_11_24_170844_data_link_existing_blog_requests_and_images.php'))->up();
}

/** Une generation terminee dont l'etape des images a rendu `$imageId` comme image principale. */
function finishedWithMainImage(array $attributes, int $imageId): BlogGeneration
{
    $generation = BlogGeneration::factory()->completed()->create($attributes);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('images', 1)->completed()
        ->create(['output' => ['images' => [['number' => 0, 'image_id' => $imageId], ['number' => 1, 'image_id' => $imageId + 1000]]]]);

    return $generation;
}

it('links past requests to their article and their main image to them, and marks them received', function () {
    $post = BlogPost::factory()->create();
    $requested = BlogImage::factory()->create(['blog_post_id' => null]);
    $byTheArticle = BlogImage::factory()->create(['blog_post_id' => $post->id]);

    $imageRequest = finishedWithMainImage(['type' => BlogGenerationType::Image, 'payload' => ['image_request' => ['post_id' => $post->id]]], $requested->id);
    $article = finishedWithMainImage(['blog_post_id' => $post->id], $byTheArticle->id);
    $translation = BlogGeneration::factory()->completed()->create(['type' => BlogGenerationType::Translation, 'payload' => ['translation' => ['post_id' => $post->id]]]);
    $forADeletedArticle = BlogGeneration::factory()->completed()->create(['type' => BlogGenerationType::Image, 'payload' => ['image_request' => ['post_id' => 999_999]]]);

    runRequestLinksBackfill();

    expect($imageRequest->refresh()->source_blog_post_id)->toBe($post->id)
        ->and($translation->refresh()->source_blog_post_id)->toBe($post->id)
        ->and($forADeletedArticle->refresh()->source_blog_post_id)->toBeNull()
        ->and($requested->refresh()->generation_id)->toBe($imageRequest->id)
        ->and($byTheArticle->refresh()->generation_id)->toBe($article->id)
        // Seules les demandes sont remises : rien ne surgira au retour sur l'article.
        ->and($imageRequest->delivered_at)->not->toBeNull()
        ->and($translation->delivered_at)->not->toBeNull()
        ->and($article->refresh()->delivered_at)->toBeNull();
});
