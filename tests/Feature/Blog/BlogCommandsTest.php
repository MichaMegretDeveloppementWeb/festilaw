<?php

use App\Actions\Blog\Image\StoreBlogImageAction;
use App\Data\Blog\StoreBlogImageData;
use App\Enums\Blog\BlogImageUsage;
use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use App\Services\System\ProductionSafetyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BlogImageFiles;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
    BlogPost::flushEventListeners();
    BlogImageFiles::cleanup();
});

describe('festilaw:blog-publish-scheduled', function () {
    it('publishes the posts whose time has come, and only those', function () {
        CarbonImmutable::setTestNow('2026-12-01 10:00:00');
        $due = BlogPost::factory()->scheduled(CarbonImmutable::parse('2026-12-01 09:59:00'))->create();
        $later = BlogPost::factory()->scheduled(CarbonImmutable::parse('2026-12-01 10:01:00'))->create();
        $draft = BlogPost::factory()->ready()->create();

        $this->artisan('festilaw:blog-publish-scheduled')
            ->expectsOutputToContain('1 post(s) published')
            ->assertSuccessful();

        expect($due->fresh()->status)->toBe(BlogPostStatus::Published)
            ->and($due->fresh()->published_at->toDateTimeString())->toBe('2026-12-01 10:00:00')
            ->and($later->fresh()->status)->toBe(BlogPostStatus::Scheduled)
            ->and($draft->fresh()->status)->toBe(BlogPostStatus::Draft);
    });

    it('has no further effect when it runs again', function () {
        CarbonImmutable::setTestNow('2026-12-01 10:00:00');
        $post = BlogPost::factory()->scheduled(CarbonImmutable::parse('2026-12-01 09:00:00'))->create();

        $this->artisan('festilaw:blog-publish-scheduled')->assertSuccessful();

        CarbonImmutable::setTestNow('2026-12-01 10:01:00');
        $this->artisan('festilaw:blog-publish-scheduled')
            ->doesntExpectOutputToContain('published')
            ->assertSuccessful();

        expect($post->fresh()->published_at->toDateTimeString())->toBe('2026-12-01 10:00:00');
    });

    it('sends a post that is no longer ready back to draft instead of failing every minute', function () {
        $post = BlogPost::factory()->scheduled(CarbonImmutable::now()->subMinute())->create();
        DB::table('blog_posts')->where('id', $post->id)->update(['featured_image_id' => null]);
        Log::spy();

        $this->artisan('festilaw:blog-publish-scheduled')
            ->expectsOutputToContain('1 sent back to draft')
            ->assertSuccessful();

        expect($post->fresh()->status)->toBe(BlogPostStatus::Draft)
            ->and($post->fresh()->scheduled_for)->toBeNull();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $context['post_id'] === $post->id && str_contains($context['reason'], 'image principale'))
            ->once();
    });

    it('carries on with the other posts when one fails, and reports the failure', function () {
        $broken = BlogPost::factory()->scheduled(CarbonImmutable::now()->subMinutes(2))->create();
        $healthy = BlogPost::factory()->scheduled(CarbonImmutable::now()->subMinute())->create();

        BlogPost::updating(function (BlogPost $post) use ($broken): void {
            if ($post->id === $broken->id) {
                throw new RuntimeException('database hiccup (test)');
            }
        });
        Log::spy();

        $this->artisan('festilaw:blog-publish-scheduled')
            ->expectsOutputToContain('1 post(s) published, 0 sent back to draft, 1 failed')
            ->assertFailed();

        expect($healthy->fresh()->status)->toBe(BlogPostStatus::Published)
            ->and($broken->fresh()->status)->toBe(BlogPostStatus::Scheduled);

        Log::shouldHaveReceived('error')->once();
    });

    it('is scheduled every minute with a lock that expires after five minutes', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'festilaw:blog-publish-scheduled'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('* * * * *')
            ->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->expiresAt)->toBe(5);
    });
});

describe('festilaw:blog-purge-orphan-images', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    /** Image reellement stockee (ligne + fichiers), vieillie de `$hours` heures. */
    function agedBlogImage(int $hours, ?int $postId = null): BlogImage
    {
        $image = app(StoreBlogImageAction::class)->execute(new StoreBlogImageData(BlogImageFiles::jpeg(600, 400), BlogImageUsage::Content));

        DB::table('blog_images')->where('id', $image->id)->update([
            'blog_post_id' => $postId,
            'updated_at' => now()->subHours($hours),
        ]);

        return $image;
    }

    /** Dossier d'image present sur le disque sans ligne en base, vieilli de `$hours` heures. */
    function strayBlogFolder(string $uuid, int $hours): void
    {
        $path = "blog/{$uuid}/left-480.webp";
        Storage::disk('public')->put($path, 'left behind');
        touch(Storage::disk('public')->path($path), time() - $hours * 3600);
    }

    it('deletes the images left without a post for more than a day, rows and files', function () {
        $oldOrphan = agedBlogImage(hours: 30);
        $recentOrphan = agedBlogImage(hours: 2);
        $attached = agedBlogImage(hours: 300, postId: BlogPost::factory()->create()->id);

        $this->artisan('festilaw:blog-purge-orphan-images')
            ->expectsOutputToContain('1 orphan image(s) deleted, 0 folder(s) without row removed, 0 failed')
            ->assertSuccessful();

        expect(BlogImage::query()->orderBy('id')->pluck('id')->all())->toBe([$recentOrphan->id, $attached->id])
            ->and(Storage::disk('public')->directories('blog'))->toHaveCount(2)
            ->and(Storage::disk('public')->exists("blog/{$oldOrphan->uuid}"))->toBeFalse();
    });

    it('removes old folders that have no row, and leaves recent ones alone', function () {
        strayBlogFolder('aaaaaaaa-0000-0000-0000-000000000001', hours: 48);
        strayBlogFolder('aaaaaaaa-0000-0000-0000-000000000002', hours: 1);
        $kept = agedBlogImage(hours: 1);

        $this->artisan('festilaw:blog-purge-orphan-images')
            ->expectsOutputToContain('0 orphan image(s) deleted, 1 folder(s) without row removed')
            ->assertSuccessful();

        expect(Storage::disk('public')->directories('blog'))->toEqualCanonicalizing([
            'blog/aaaaaaaa-0000-0000-0000-000000000002',
            "blog/{$kept->uuid}",
        ]);
    });

    it('lists what it would delete without deleting anything in dry mode', function () {
        $orphan = agedBlogImage(hours: 30);
        strayBlogFolder('aaaaaaaa-0000-0000-0000-000000000001', hours: 48);

        $this->artisan('festilaw:blog-purge-orphan-images --dry')
            ->expectsOutputToContain("Would delete orphan image #{$orphan->id}.")
            ->expectsOutputToContain('Would delete folder without image row: aaaaaaaa-0000-0000-0000-000000000001.')
            ->expectsOutputToContain('Blog (dry run): 1 orphan image(s) deleted, 1 folder(s) without row removed, 0 failed')
            ->assertSuccessful();

        expect(BlogImage::query()->count())->toBe(1)
            ->and(Storage::disk('public')->directories('blog'))->toHaveCount(2);
    });

    it('reports a failure when a stray folder cannot be removed', function () {
        strayBlogFolder('aaaaaaaa-0000-0000-0000-000000000001', hours: 48);
        $files = Storage::disk('public')->allFiles('blog');
        BlogImageFiles::failingDisk(failDirectoryDeletes: true);
        foreach ($files as $file) {
            Storage::disk('public')->put($file, 'left behind');
            touch(Storage::disk('public')->path($file), time() - 48 * 3600);
        }

        $this->artisan('festilaw:blog-purge-orphan-images')
            ->expectsOutputToContain('1 failed')
            ->assertFailed();
    });

    it('is scheduled once a day with a bounded lock', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'festilaw:blog-purge-orphan-images'));

        expect($event->expression)->toBe('30 3 * * *')
            ->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->expiresAt)->toBe(60);
    });
});

describe('festilaw:blog-rerender', function () {
    it('rebuilds the stored html that is out of date and leaves the rest untouched', function () {
        $post = BlogPost::factory()->create();
        $stale = BlogPostTranslation::factory()->for($post, 'post')->create(['locale' => 'en', 'content_html' => '<p>old engine</p>']);
        $fresh = BlogPostTranslation::factory()->for($post, 'post')->create(['locale' => 'fr']);

        $this->artisan('festilaw:blog-rerender')
            ->expectsOutputToContain('Blog: 1 post(s) read, 1 language version(s) re-rendered.')
            ->assertSuccessful();

        expect($stale->fresh()->content_html)->toBe('<p>'.e($stale->content['content'][0]['content'][0]['text']).'</p>')
            ->and($fresh->fresh()->updated_at->equalTo($fresh->updated_at))->toBeTrue();

        $this->artisan('festilaw:blog-rerender')->expectsOutputToContain('0 language version(s) re-rendered')->assertSuccessful();
    });

    it('only counts in dry mode', function () {
        $translation = BlogPostTranslation::factory()->create(['content_html' => '<p>old engine</p>']);

        $this->artisan('festilaw:blog-rerender --dry')
            ->expectsOutputToContain('Blog (dry run): 1 post(s) read, 1 language version(s) re-rendered.')
            ->assertSuccessful();

        expect($translation->fresh()->content_html)->toBe('<p>old engine</p>');
    });
});

it('counts the image library among the production requirements', function () {
    $violations = implode("\n", app(ProductionSafetyService::class)->violations());

    // GD avec WebP est present sur ce poste : la regle existe et ne se declenche pas.
    expect(function_exists('imagewebp'))->toBeTrue()
        ->and($violations)->not->toContain('WebP');
});
