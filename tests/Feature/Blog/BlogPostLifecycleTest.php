<?php

use App\Actions\Blog\Image\StoreBlogImageAction;
use App\Actions\Blog\Post\DeleteBlogPostAction;
use App\Actions\Blog\Post\PublishBlogPostAction;
use App\Actions\Blog\Post\ScheduleBlogPostAction;
use App\Actions\Blog\Post\UnpublishBlogPostAction;
use App\Data\Blog\StoreBlogImageData;
use App\Enums\Blog\BlogImageUsage;
use App\Enums\Blog\BlogPostStatus;
use App\Enums\Blog\BlogSlugOutcome;
use App\Exceptions\Blog\BlogPostException;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\BlogPostRetiredSlug;
use App\Models\BlogPostTranslation;
use App\Services\Blog\Slug\BlogPostSlugLookupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BlogImageFiles;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
    BlogImageFiles::cleanup();
});

describe('publishing', function () {
    it('publishes a ready post and dates its first publication', function () {
        CarbonImmutable::setTestNow('2026-12-01 09:30:00');
        $post = BlogPost::factory()->scheduled()->create();

        $published = app(PublishBlogPostAction::class)->execute($post->id);

        expect($published->status)->toBe(BlogPostStatus::Published)
            ->and($published->published_at->toDateTimeString())->toBe('2026-12-01 09:30:00')
            ->and($published->scheduled_for)->toBeNull();
    });

    it('can be replayed without moving the publication date', function () {
        $post = BlogPost::factory()->published(CarbonImmutable::parse('2026-11-20 08:00:00'))->create();

        $again = app(PublishBlogPostAction::class)->execute($post->id);

        expect($again->published_at->toDateTimeString())->toBe('2026-11-20 08:00:00');
    });

    it('refuses to publish while one of the three languages is not written, and says what is missing', function () {
        $post = BlogPost::factory()->ready()->create();
        $post->translations()->where('locale', 'es')->update(['content' => null, 'content_html' => null, 'word_count' => 0]);
        $post->translations()->where('locale', 'en')->update(['title' => null]);

        try {
            app(PublishBlogPostAction::class)->execute($post->id);
            $this->fail('The post should not have been published.');
        } catch (BlogPostException $e) {
            expect($e->getUserMessage())->toContain('titre (anglais)')->toContain('contenu (espagnol)')
                ->and($e->getUserMessage())->not->toContain('français');
        }

        expect($post->fresh()->status)->toBe(BlogPostStatus::Draft);
    });

    it('refuses to publish without a category or a featured image', function (string $column, string $expected) {
        $post = BlogPost::factory()->ready()->create();
        DB::table('blog_posts')->where('id', $post->id)->update([$column => null]);

        expect(fn () => app(PublishBlogPostAction::class)->execute($post->id))
            ->toThrow(fn (BlogPostException $e) => expect($e->getUserMessage())->toContain($expected));
    })->with([
        ['blog_category_id', 'catégorie'],
        ['featured_image_id', 'image principale'],
    ]);

    it('reports a post that does not exist', function () {
        expect(fn () => app(PublishBlogPostAction::class)->execute(999))->toThrow(BlogPostException::class, 'not found');
    });
});

describe('scheduling', function () {
    it('schedules a ready post for a future date, stored in utc', function () {
        CarbonImmutable::setTestNow('2026-12-01 09:00:00');
        $post = BlogPost::factory()->ready()->create();

        $scheduled = app(ScheduleBlogPostAction::class)->execute($post->id, CarbonImmutable::parse('2026-12-05 10:00:00', 'Europe/Paris'));

        expect($scheduled->status)->toBe(BlogPostStatus::Scheduled)
            ->and($scheduled->fresh()->scheduled_for->toDateTimeString())->toBe('2026-12-05 09:00:00')
            ->and($scheduled->published_at)->toBeNull();
    });

    it('refuses a date that is not in the future', function () {
        CarbonImmutable::setTestNow('2026-12-01 09:00:00');
        $post = BlogPost::factory()->ready()->create();

        expect(fn () => app(ScheduleBlogPostAction::class)->execute($post->id, CarbonImmutable::parse('2026-12-01 08:59:00')))
            ->toThrow(BlogPostException::class, 'in the past');
    });

    it('refuses to schedule an incomplete post or one that is already published', function () {
        $draft = BlogPost::factory()->withTranslations()->create();
        $published = BlogPost::factory()->published()->create();
        $tomorrow = CarbonImmutable::now()->addDay();

        expect(fn () => app(ScheduleBlogPostAction::class)->execute($draft->id, $tomorrow))->toThrow(BlogPostException::class, 'not ready')
            ->and(fn () => app(ScheduleBlogPostAction::class)->execute($published->id, $tomorrow))->toThrow(BlogPostException::class, 'already published');
    });
});

describe('unpublishing', function () {
    it('takes a published post back to draft and keeps the date of its first publication', function () {
        $post = BlogPost::factory()->published(CarbonImmutable::parse('2026-11-20 08:00:00'))->create();

        $draft = app(UnpublishBlogPostAction::class)->execute($post->id);

        expect($draft->status)->toBe(BlogPostStatus::Draft)
            ->and($draft->published_at->toDateTimeString())->toBe('2026-11-20 08:00:00')
            ->and($draft->hasBeenPublished())->toBeTrue();
    });

    it('keeps the original date when the post is published again', function () {
        CarbonImmutable::setTestNow('2026-12-10 12:00:00');
        $post = BlogPost::factory()->published(CarbonImmutable::parse('2026-11-20 08:00:00'))->create();

        app(UnpublishBlogPostAction::class)->execute($post->id);
        $republished = app(PublishBlogPostAction::class)->execute($post->id);

        expect($republished->published_at->toDateTimeString())->toBe('2026-11-20 08:00:00');
    });

    it('cancels a schedule, and leaves a draft as it is', function () {
        $scheduled = BlogPost::factory()->scheduled()->create();
        $draft = BlogPost::factory()->create();

        $cancelled = app(UnpublishBlogPostAction::class)->execute($scheduled->id);

        expect($cancelled->status)->toBe(BlogPostStatus::Draft)
            ->and($cancelled->scheduled_for)->toBeNull()
            ->and(app(UnpublishBlogPostAction::class)->execute($draft->id)->status)->toBe(BlogPostStatus::Draft);
    });
});

describe('deleting', function () {
    it('deletes a draft with its texts and leaves no trace of its addresses', function () {
        $post = BlogPost::factory()->withTranslations()->create();

        expect(app(DeleteBlogPostAction::class)->execute($post->id))->toBeTrue()
            ->and(BlogPost::query()->count())->toBe(0)
            ->and(BlogPostTranslation::query()->count())->toBe(0)
            ->and(BlogPostRetiredSlug::query()->count())->toBe(0);
    });

    it('answers gone at every address of a deleted post that had been published', function () {
        $post = BlogPost::factory()->published()->create()->load('translations');
        $currentSlug = $post->translationFor('fr')->slug;
        BlogPostRetiredSlug::factory()->create(['blog_post_id' => $post->id, 'locale' => 'fr', 'slug' => 'ancienne-adresse']);

        app(DeleteBlogPostAction::class)->execute($post->id);

        $lookup = app(BlogPostSlugLookupService::class);

        expect($lookup->resolve('fr', $currentSlug)->outcome)->toBe(BlogSlugOutcome::Gone)
            ->and($lookup->resolve('fr', 'ancienne-adresse')->outcome)->toBe(BlogSlugOutcome::Gone)
            ->and(BlogPostRetiredSlug::query()->whereNull('blog_post_id')->count())->toBe(4);
    });

    it('deletes the images of the post, rows and files', function () {
        Storage::fake('public');
        // Les sources non decoupees vont sur le disque prive (audit du 23/11).
        Storage::fake('local');
        $image = app(StoreBlogImageAction::class)->execute(new StoreBlogImageData(BlogImageFiles::jpeg(1300, 700), BlogImageUsage::Featured));
        $post = BlogPost::factory()->withTranslations()->create(['featured_image_id' => $image->id]);
        $image->update(['blog_post_id' => $post->id]);

        expect(Storage::disk('public')->allFiles('blog'))->not->toBeEmpty();

        app(DeleteBlogPostAction::class)->execute($post->id);

        expect(BlogImage::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles('blog'))->toBe([]);
    });

    it('still deletes the post when the files of an image resist, and logs it', function () {
        Storage::fake('public');
        // Les sources non decoupees vont sur le disque prive (audit du 23/11).
        Storage::fake('local');
        $image = app(StoreBlogImageAction::class)->execute(new StoreBlogImageData(BlogImageFiles::jpeg(900, 600), BlogImageUsage::Content));
        $post = BlogPost::factory()->create();
        $image->update(['blog_post_id' => $post->id]);

        BlogImageFiles::failingDisk(failDirectoryDeletes: true);
        Log::spy();

        expect(app(DeleteBlogPostAction::class)->execute($post->id))->toBeTrue()
            ->and(BlogPost::query()->count())->toBe(0)
            ->and(BlogImage::query()->count())->toBe(0);

        Log::shouldHaveReceived('warning')->once();
    });

    it('can be replayed: deleting a post already gone does nothing', function () {
        expect(app(DeleteBlogPostAction::class)->execute(999))->toBeFalse();
    });
});
