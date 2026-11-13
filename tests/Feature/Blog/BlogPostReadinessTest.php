<?php

use App\Data\Blog\BlogReadinessIssue;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Services\Blog\Post\BlogAuthorProvider;
use App\Services\Blog\Post\BlogPostReadinessCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function blogReadiness(BlogPost $post)
{
    return app(BlogPostReadinessCalculator::class)->calculate($post->fresh()->load(['translations', 'images']));
}

it('finds a complete post ready, without running any query', function () {
    $post = BlogPost::factory()->ready()->create()->load(['translations', 'images']);

    DB::enableQueryLog();
    $readiness = app(BlogPostReadinessCalculator::class)->calculate($post);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($readiness->isReady())->toBeTrue()
        ->and($readiness->blockerSummary())->toBe('')
        ->and($queries)->toBe(0);
});

it('blocks on each missing piece, language by language', function () {
    $post = BlogPost::factory()->ready()->create();
    $post->translations()->where('locale', 'fr')->update(['slug' => null]);
    $post->translations()->where('locale', 'es')->delete();
    DB::table('blog_posts')->where('id', $post->id)->update(['blog_category_id' => null, 'featured_image_id' => null]);

    $readiness = blogReadiness($post);

    expect($readiness->isReady())->toBeFalse()
        ->and($readiness->hasBlocker(BlogReadinessIssue::SLUG, 'fr'))->toBeTrue()
        ->and($readiness->hasBlocker(BlogReadinessIssue::TITLE, 'es'))->toBeTrue()
        ->and($readiness->hasBlocker(BlogReadinessIssue::SLUG, 'es'))->toBeTrue()
        ->and($readiness->hasBlocker(BlogReadinessIssue::CONTENT, 'es'))->toBeTrue()
        ->and($readiness->hasBlocker(BlogReadinessIssue::CATEGORY))->toBeTrue()
        ->and($readiness->hasBlocker(BlogReadinessIssue::FEATURED_IMAGE))->toBeTrue()
        ->and($readiness->hasBlocker(BlogReadinessIssue::TITLE, 'en'))->toBeFalse()
        ->and($readiness->blockerSummary())->toBe('adresse (français), titre (espagnol), adresse (espagnol), contenu (espagnol), catégorie, image principale');
});

it('warns without blocking about what would be better filled in', function () {
    $post = BlogPost::factory()->ready()->create();
    $post->translations()->where('locale', 'en')->update(['excerpt' => null]);
    $undescribed = BlogImage::factory()->create(['blog_post_id' => $post->id, 'alt' => ['en' => 'Only english']]);

    $readiness = blogReadiness($post);

    expect($readiness->isReady())->toBeTrue()
        ->and($readiness->hasWarning(BlogReadinessIssue::EXCERPT, 'en'))->toBeTrue()
        ->and($readiness->hasWarning(BlogReadinessIssue::EXCERPT, 'fr'))->toBeFalse()
        ->and($readiness->hasWarning(BlogReadinessIssue::META_DESCRIPTION, 'fr'))->toBeTrue()
        ->and($readiness->hasWarning(BlogReadinessIssue::SOURCES))->toBeTrue()
        ->and(collect($readiness->warnings)->where('field', BlogReadinessIssue::IMAGE_ALT)->map(fn (BlogReadinessIssue $issue): array => [$issue->imageId, $issue->locale])->values()->all())
        ->toBe([[$undescribed->id, 'fr'], [$undescribed->id, 'es']]);
});

it('gives the author with the job title of the requested language', function () {
    $author = app(BlogAuthorProvider::class);

    expect($author->forLocale('en')->name)->toBe('Laëtitia Redonet')
        ->and($author->forLocale('en')->jobTitle)->toBe('EU Market Access & Compliance Advisor')
        ->and($author->forLocale('fr')->jobTitle)->toBe('Conseillère en accès au marché européen et conformité')
        ->and($author->forLocale('es')->jobTitle)->toBe('Asesora de acceso al mercado europeo y cumplimiento normativo')
        ->and($author->forLocale('de')->jobTitle)->toBe('EU Market Access & Compliance Advisor');
});

it('never presents the author as a lawyer', function () {
    $titles = implode(' ', config('blog.author.job_title'));

    expect(mb_strtolower($titles))->not->toContain('juriste')->not->toContain('lawyer')->not->toContain('avocat')->not->toContain('abogad');
});
