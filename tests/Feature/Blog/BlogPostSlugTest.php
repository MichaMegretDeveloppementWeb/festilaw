<?php

use App\Enums\Blog\BlogSlugOutcome;
use App\Exceptions\Blog\BlogSlugException;
use App\Models\BlogPost;
use App\Models\BlogPostRetiredSlug;
use App\Models\BlogPostTranslation;
use App\Services\Blog\Slug\BlogPostSlugAssigner;
use App\Services\Blog\Slug\BlogPostSlugLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Cree un article (brouillon par defaut) avec une traduction portant ce slug. */
function blogPostWithSlug(string $locale, string $slug, string $state = 'draft'): BlogPost
{
    $post = match ($state) {
        'published' => BlogPost::factory()->published()->create(),
        default => BlogPost::factory()->create(),
    };

    BlogPostTranslation::query()->updateOrCreate(
        ['blog_post_id' => $post->id, 'locale' => $locale],
        BlogPostTranslation::factory()->make(['locale' => $locale, 'slug' => $slug])->only(['title', 'slug', 'excerpt', 'content', 'content_html', 'word_count']),
    );

    return $post->load('translations');
}

describe('choosing a slug', function () {
    it('generates the slug from the title when none is typed', function () {
        $slug = app(BlogPostSlugAssigner::class)->resolve(null, 'fr', null, 'L\'étiquetage GPSR : ce qu\'il faut savoir');

        expect($slug)->toBe('l-etiquetage-gpsr-ce-qu-il-faut-savoir');
    });

    it('returns no slug while the language has neither slug nor title', function () {
        expect(app(BlogPostSlugAssigner::class)->resolve(null, 'fr', '', null))->toBeNull()
            ->and(app(BlogPostSlugAssigner::class)->resolve(null, 'fr', null, '¿?'))->toBeNull();
    });

    it('numbers a generated slug already used by another post, drafts included', function () {
        blogPostWithSlug('fr', 'guide-gpsr');

        expect(app(BlogPostSlugAssigner::class)->resolve(null, 'fr', null, 'Guide GPSR'))->toBe('guide-gpsr-2');
    });

    it('treats the same slug in another language as free', function () {
        blogPostWithSlug('fr', 'guide-gpsr');

        expect(app(BlogPostSlugAssigner::class)->resolve(null, 'es', null, 'Guide GPSR'))->toBe('guide-gpsr');
    });

    it('normalises a typed slug and keeps it as chosen', function () {
        expect(app(BlogPostSlugAssigner::class)->resolve(null, 'fr', 'Mon Article !', 'Un tout autre titre'))->toBe('mon-article');
    });

    it('refuses a typed slug already used by another post instead of numbering it', function () {
        blogPostWithSlug('fr', 'guide-gpsr');

        expect(fn () => app(BlogPostSlugAssigner::class)->resolve(null, 'fr', 'guide-gpsr', 'Guide'))
            ->toThrow(BlogSlugException::class, 'already taken');
    });

    it('lets a post keep its own slug', function () {
        $post = blogPostWithSlug('fr', 'guide-gpsr');

        expect(app(BlogPostSlugAssigner::class)->resolve($post->id, 'fr', 'guide-gpsr', 'Guide'))->toBe('guide-gpsr');
    });

    it('refuses a reserved word typed as slug, in every language', function (string $locale, string $word) {
        expect(fn () => app(BlogPostSlugAssigner::class)->resolve(null, $locale, $word, 'Titre'))
            ->toThrow(BlogSlugException::class, 'reserved');
    })->with([
        ['en', 'category'],
        ['en', 'categorie'],
        ['fr', 'categoria'],
        ['es', 'page'],
    ]);

    it('numbers a generated slug that would be a reserved word', function () {
        expect(app(BlogPostSlugAssigner::class)->resolve(null, 'en', null, 'Page'))->toBe('page-2');
    });

    it('protects the old slug of another post but frees the slug of a deleted post', function () {
        $other = BlogPost::factory()->published()->create();
        BlogPostRetiredSlug::factory()->create(['blog_post_id' => $other->id, 'locale' => 'fr', 'slug' => 'ancien-titre']);
        BlogPostRetiredSlug::factory()->gone()->create(['locale' => 'fr', 'slug' => 'article-supprime']);

        $assigner = app(BlogPostSlugAssigner::class);

        expect(fn () => $assigner->resolve(null, 'fr', 'ancien-titre', 'Titre'))->toThrow(BlogSlugException::class)
            ->and($assigner->resolve(null, 'fr', null, 'Ancien titre'))->toBe('ancien-titre-2')
            ->and($assigner->resolve(null, 'fr', 'article-supprime', 'Titre'))->toBe('article-supprime');
    });
});

describe('keeping the slug history', function () {
    it('keeps no trace of a slug changed before the first publication', function () {
        $post = blogPostWithSlug('fr', 'premier-jet');

        app(BlogPostSlugAssigner::class)->record($post, 'fr', 'premier-jet', 'titre-final');

        expect(BlogPostRetiredSlug::query()->count())->toBe(0);
    });

    it('retires the old slug once the post has been published', function () {
        $post = blogPostWithSlug('fr', 'ancien', 'published');

        app(BlogPostSlugAssigner::class)->record($post, 'fr', 'ancien', 'nouveau');

        $retired = BlogPostRetiredSlug::query()->sole();

        expect($retired->slug)->toBe('ancien')
            ->and($retired->locale)->toBe('fr')
            ->and($retired->blog_post_id)->toBe($post->id);
    });

    it('takes a slug back out of the history when the post reuses it', function () {
        $post = blogPostWithSlug('fr', 'b', 'published');
        BlogPostRetiredSlug::factory()->create(['blog_post_id' => $post->id, 'locale' => 'fr', 'slug' => 'a']);

        app(BlogPostSlugAssigner::class)->record($post, 'fr', 'b', 'a');

        expect(BlogPostRetiredSlug::query()->pluck('slug')->all())->toBe(['b']);
    });

    it('drops the tombstone of a deleted post when its slug is assigned again', function () {
        BlogPostRetiredSlug::factory()->gone()->create(['locale' => 'fr', 'slug' => 'reprise']);
        $post = blogPostWithSlug('fr', 'provisoire');

        app(BlogPostSlugAssigner::class)->record($post, 'fr', 'provisoire', 'reprise');

        expect(BlogPostRetiredSlug::query()->count())->toBe(0);
    });

    it('does nothing when the slug did not change', function () {
        $post = blogPostWithSlug('fr', 'stable', 'published');

        app(BlogPostSlugAssigner::class)->record($post, 'fr', 'stable', 'stable');

        expect(BlogPostRetiredSlug::query()->count())->toBe(0);
    });

    it('turns the live slugs of a published post into tombstones before its deletion', function () {
        $post = BlogPost::factory()->published()->create()->load('translations');
        $slugs = $post->translations->pluck('slug', 'locale');

        app(BlogPostSlugAssigner::class)->entomb($post);

        expect(BlogPostRetiredSlug::query()->whereNull('blog_post_id')->count())->toBe(3)
            ->and(BlogPostRetiredSlug::query()->where('locale', 'es')->value('slug'))->toBe($slugs['es']);
    });

    it('leaves no tombstone for a post that was never published', function () {
        $post = BlogPost::factory()->withTranslations()->create()->load('translations');

        app(BlogPostSlugAssigner::class)->entomb($post);

        expect(BlogPostRetiredSlug::query()->count())->toBe(0);
    });
});

describe('resolving a requested slug', function () {
    it('finds the published post that owns the slug', function () {
        $post = blogPostWithSlug('fr', 'guide-gpsr', 'published');

        $resolution = app(BlogPostSlugLookupService::class)->resolve('fr', 'guide-gpsr');

        expect($resolution->outcome)->toBe(BlogSlugOutcome::Found)
            ->and($resolution->postId)->toBe($post->id);
    });

    it('never reveals a draft or a scheduled post', function () {
        blogPostWithSlug('fr', 'brouillon');
        $scheduled = BlogPost::factory()->scheduled()->create()->load('translations');

        $lookup = app(BlogPostSlugLookupService::class);

        expect($lookup->resolve('fr', 'brouillon')->outcome)->toBe(BlogSlugOutcome::NotFound)
            ->and($lookup->resolve('fr', $scheduled->translationFor('fr')->slug)->outcome)->toBe(BlogSlugOutcome::NotFound);
    });

    it('does not find a slug in a language it does not belong to', function () {
        blogPostWithSlug('fr', 'guide-gpsr', 'published');

        expect(app(BlogPostSlugLookupService::class)->resolve('es', 'guide-gpsr')->outcome)->toBe(BlogSlugOutcome::NotFound);
    });

    it('redirects every old slug straight to the current one, in a single hop', function () {
        $post = blogPostWithSlug('fr', 'a', 'published');
        $assigner = app(BlogPostSlugAssigner::class);

        foreach ([['a', 'b'], ['b', 'c']] as [$from, $to]) {
            $post->translationFor('fr')->update(['slug' => $to]);
            $assigner->record($post, 'fr', $from, $to);
        }

        $lookup = app(BlogPostSlugLookupService::class);

        expect($lookup->resolve('fr', 'a')->outcome)->toBe(BlogSlugOutcome::Moved)
            ->and($lookup->resolve('fr', 'a')->currentSlug)->toBe('c')
            ->and($lookup->resolve('fr', 'b')->currentSlug)->toBe('c')
            ->and($lookup->resolve('fr', 'c')->outcome)->toBe(BlogSlugOutcome::Found);
    });

    it('answers not found for the old slug of a post that is no longer published', function () {
        $post = blogPostWithSlug('fr', 'actuel');
        BlogPostRetiredSlug::factory()->create(['blog_post_id' => $post->id, 'locale' => 'fr', 'slug' => 'ancien']);

        expect(app(BlogPostSlugLookupService::class)->resolve('fr', 'ancien')->outcome)->toBe(BlogSlugOutcome::NotFound);
    });

    it('answers gone for the slugs of a deleted post', function () {
        $post = blogPostWithSlug('fr', 'actuel', 'published');
        BlogPostRetiredSlug::factory()->create(['blog_post_id' => $post->id, 'locale' => 'fr', 'slug' => 'ancien']);

        app(BlogPostSlugAssigner::class)->entomb($post);
        $post->delete();

        $lookup = app(BlogPostSlugLookupService::class);

        expect($lookup->resolve('fr', 'actuel')->outcome)->toBe(BlogSlugOutcome::Gone)
            ->and($lookup->resolve('fr', 'ancien')->outcome)->toBe(BlogSlugOutcome::Gone)
            ->and($lookup->resolve('fr', 'jamais-vu')->outcome)->toBe(BlogSlugOutcome::NotFound);
    });

    it('rejects a malformed slug without matching it loosely', function (string $slug) {
        blogPostWithSlug('fr', 'guide-gpsr', 'published');

        expect(app(BlogPostSlugLookupService::class)->resolve('fr', $slug)->outcome)->toBe(BlogSlugOutcome::NotFound);
    })->with(['Guide-GPSR', 'guide-gpsr-', '-guide-gpsr', 'guide--gpsr', 'guide gpsr', 'guidé-gpsr', '']);
});
