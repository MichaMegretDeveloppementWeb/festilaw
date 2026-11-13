<?php

use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Data\Blog\BlogPostTranslationData;
use App\Data\Blog\BlogSourceData;
use App\Data\Blog\SaveBlogPostData;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Enums\Blog\BlogSlugOutcome;
use App\Exceptions\Blog\BlogContentException;
use App\Exceptions\Blog\BlogImageException;
use App\Exceptions\Blog\BlogPostException;
use App\Exceptions\Blog\BlogSlugException;
use App\Models\BlogCategory;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\BlogPostRetiredSlug;
use App\Models\BlogPostTranslation;
use App\Services\Blog\Content\BlogMarkdownConverter;
use App\Services\Blog\Slug\BlogPostSlugLookupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param array<int, array<string, mixed>> $blocks */
function blogContent(array ...$blocks): array
{
    return ['type' => 'doc', 'content' => $blocks];
}

function blogContentParagraph(string $text): array
{
    return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
}

/** @param array<string, mixed> $overrides */
function blogTranslationInput(string $title, array $overrides = []): BlogPostTranslationData
{
    return new BlogPostTranslationData(...array_merge([
        'title' => $title,
        'excerpt' => "Excerpt of {$title}",
        'content' => blogContent(blogContentParagraph("Body of {$title} in a few words.")),
    ], $overrides));
}

/** Les trois langues redigees. @return array<string, BlogPostTranslationData> */
function blogThreeLanguages(string $suffix = ''): array
{
    return [
        'en' => blogTranslationInput("GPSR labelling guide{$suffix}"),
        'fr' => blogTranslationInput("Guide de l'étiquetage GPSR{$suffix}"),
        'es' => blogTranslationInput("Guía del etiquetado GPSR{$suffix}"),
    ];
}

/** @param array<string, mixed> $arguments */
function saveBlogPost(array $arguments): BlogPost
{
    return app(SaveBlogPostAction::class)->execute(new SaveBlogPostData(...array_merge(['postId' => null, 'translations' => []], $arguments)));
}

/** Donnees d'enregistrement qui reprennent l'article tel qu'il est (formulaire recharge puis renvoye). @return array<string, mixed> */
function blogPostAsInput(BlogPost $post): array
{
    $post->load('translations');

    return [
        'postId' => $post->id,
        'categoryId' => $post->blog_category_id,
        'featuredImageId' => $post->featured_image_id,
        'shareImageId' => $post->share_image_id,
        'translations' => $post->translations->mapWithKeys(fn (BlogPostTranslation $translation): array => [
            $translation->locale => new BlogPostTranslationData(
                title: $translation->title,
                slug: $translation->slug,
                excerpt: $translation->excerpt,
                content: $translation->content,
                metaTitle: $translation->meta_title,
                metaDescription: $translation->meta_description,
            ),
        ])->all(),
    ];
}

describe('creating and editing a draft', function () {
    it('creates a draft from a single language, with no category', function () {
        $post = saveBlogPost(['translations' => ['fr' => blogTranslationInput('L\'étiquetage GPSR : ce qu\'il faut savoir')]]);

        $french = $post->translationFor('fr');

        expect($post->status)->toBe(BlogPostStatus::Draft)
            ->and($post->origin)->toBe(BlogPostOrigin::Manual)
            ->and($post->blog_category_id)->toBeNull()
            ->and($post->translations)->toHaveCount(3)
            ->and($french->slug)->toBe('l-etiquetage-gpsr-ce-qu-il-faut-savoir')
            ->and($french->content_html)->toBe('<p>Body of L&apos;étiquetage GPSR : ce qu&apos;il faut savoir in a few words.</p>')
            ->and($french->word_count)->toBe(12) // "L'étiquetage" et "qu'il" comptent chacun pour un mot, ":" pour aucun
            ->and($french->isWritten())->toBeTrue()
            ->and($post->translationFor('en')->isWritten())->toBeFalse()
            ->and($post->translationFor('en')->slug)->toBeNull()
            ->and($post->translationFor('es')->content)->toBeNull();
    });

    it('cleans the texts and stores the canonical document with its escaped html', function () {
        $post = saveBlogPost(['translations' => ['en' => new BlogPostTranslationData(
            title: "  A   title\n on two lines ",
            excerpt: '   ',
            content: blogContent(
                ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'dropped']]],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => '<b>bold?</b> ', 'marks' => [['type' => 'underline']]],
                    ['type' => 'text', 'text' => 'link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
                ]],
            ),
            metaDescription: ' Meta ',
        )]]);

        $english = $post->translationFor('en');

        expect($english->title)->toBe('A title on two lines')
            ->and($english->excerpt)->toBeNull()
            ->and($english->meta_description)->toBe('Meta')
            ->and($english->content)->toBe(blogContent(['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<b>bold?</b> link']]]))
            ->and($english->content_html)->toBe('<p>&lt;b&gt;bold?&lt;/b&gt; link</p>');
    });

    it('keeps the slug sent back by the form and regenerates it when it is emptied', function () {
        $post = saveBlogPost(['translations' => ['fr' => blogTranslationInput('Premier titre')]]);

        $renamed = saveBlogPost([...blogPostAsInput($post), 'translations' => ['fr' => blogTranslationInput('Titre corrigé', ['slug' => 'premier-titre'])]]);
        expect($renamed->translationFor('fr')->slug)->toBe('premier-titre');

        $regenerated = saveBlogPost([...blogPostAsInput($post), 'translations' => ['fr' => blogTranslationInput('Titre corrigé', ['slug' => ''])]]);
        expect($regenerated->translationFor('fr')->slug)->toBe('titre-corrige')
            ->and(BlogPostRetiredSlug::query()->count())->toBe(0);
    });

    it('saves nothing at all when a chosen slug is already taken', function () {
        saveBlogPost(['translations' => ['fr' => blogTranslationInput('Guide GPSR')]]);

        expect(fn () => saveBlogPost(['translations' => [
            'en' => blogTranslationInput('A fine english title'),
            'fr' => blogTranslationInput('Autre article', ['slug' => 'guide-gpsr']),
        ]]))->toThrow(BlogSlugException::class);

        expect(BlogPost::query()->count())->toBe(1);
    });

    it('refuses a content above the size limit without creating the post', function () {
        config(['blog.content.max_bytes' => 500]);

        expect(fn () => saveBlogPost(['translations' => ['en' => blogTranslationInput('Long', [
            'content' => blogContent(blogContentParagraph(str_repeat('word ', 300))),
        ])]]))->toThrow(BlogContentException::class);

        expect(BlogPost::query()->count())->toBe(0);
    });

    it('refuses a category that does not exist', function () {
        expect(fn () => saveBlogPost(['categoryId' => 999, 'translations' => blogThreeLanguages()]))
            ->toThrow(BlogPostException::class, 'category');
    });

    it('reports a post that no longer exists', function () {
        expect(fn () => saveBlogPost(['postId' => 999, 'translations' => blogThreeLanguages()]))
            ->toThrow(BlogPostException::class, 'not found');
    });

    it('records the origin at creation only', function () {
        $post = saveBlogPost(['origin' => BlogPostOrigin::Ai, 'translations' => blogThreeLanguages()]);
        $saved = saveBlogPost([...blogPostAsInput($post), 'origin' => BlogPostOrigin::Manual]);

        expect($saved->fresh()->origin)->toBe(BlogPostOrigin::Ai);
    });

    it('saves a text written in markdown through the same path', function () {
        $content = app(BlogMarkdownConverter::class)->toDocument("## Why it matters\n\nSince **2024**, every product needs a [responsible person](/services).");

        $post = saveBlogPost(['origin' => BlogPostOrigin::Ai, 'translations' => ['en' => blogTranslationInput('From markdown', ['content' => $content->document])]]);

        expect($post->translationFor('en')->content_html)->toBe(
            "<h2 id=\"why-it-matters\">Why it matters</h2>\n<p>Since <strong>2024</strong>, every product needs a <a href=\"/services\">responsible person</a>.</p>"
        );
    });
});

describe('images, related posts and sources', function () {
    it('attaches the pending images the post uses and renders them with the description of each language', function () {
        $featured = BlogImage::factory()->withShare()->create();
        $inline = BlogImage::factory()->create(['alt' => ['en' => 'A label', 'fr' => 'Une étiquette']]);
        $unused = BlogImage::factory()->create();

        $content = blogContent(blogContentParagraph('Before the picture.'), ['type' => 'blogImage', 'attrs' => ['imageId' => $inline->id]]);

        $post = saveBlogPost([
            'featuredImageId' => $featured->id,
            'translations' => [
                'en' => blogTranslationInput('With image', ['content' => $content]),
                'fr' => blogTranslationInput('Avec image', ['content' => $content]),
            ],
        ]);

        expect($post->featured_image_id)->toBe($featured->id)
            ->and($post->images->pluck('id')->sort()->values()->all())->toBe([$featured->id, $inline->id])
            ->and($unused->fresh()->blog_post_id)->toBeNull()
            ->and($post->translationFor('en')->content_html)->toContain('alt="A label"')
            ->and($post->translationFor('fr')->content_html)->toContain('alt="Une étiquette"');
    });

    it('releases an image the post no longer uses', function () {
        $first = BlogImage::factory()->create();
        $second = BlogImage::factory()->create();
        $post = saveBlogPost(['featuredImageId' => $first->id, 'translations' => blogThreeLanguages()]);

        saveBlogPost([...blogPostAsInput($post), 'featuredImageId' => $second->id]);

        expect($first->fresh()->blog_post_id)->toBeNull()
            ->and($second->fresh()->blog_post_id)->toBe($post->id)
            ->and($post->fresh()->featured_image_id)->toBe($second->id);
    });

    it('refuses an image that belongs to another post or does not exist, and saves nothing', function (Closure $imageId) {
        $id = $imageId();

        expect(fn () => saveBlogPost(['translations' => ['en' => blogTranslationInput('Borrowed', [
            'content' => blogContent(['type' => 'blogImage', 'attrs' => ['imageId' => $id]], blogContentParagraph('Text.')),
        ])]]))->toThrow(BlogImageException::class);

        expect(BlogPostTranslation::query()->where('title', 'Borrowed')->exists())->toBeFalse();
    })->with([
        'image of another post' => [fn () => BlogImage::factory()->create(['blog_post_id' => BlogPost::factory()->create()->id])->id],
        'unknown image' => [fn () => 987654],
    ]);

    it('keeps related posts in the chosen order, without the post itself, duplicates or missing posts', function () {
        [$a, $b, $c] = BlogPost::factory()->count(3)->create();
        $post = saveBlogPost(['translations' => blogThreeLanguages()]);

        $saved = saveBlogPost([...blogPostAsInput($post), 'relatedPostIds' => [$c->id, $post->id, $a->id, $c->id, 424242]]);

        expect($saved->relatedPosts()->pluck('blog_posts.id')->all())->toBe([$c->id, $a->id]);

        saveBlogPost([...blogPostAsInput($post), 'relatedPostIds' => [$b->id]]);

        expect($post->relatedPosts()->pluck('blog_posts.id')->all())->toBe([$b->id]);
    });

    it('keeps only the sources that have a label and a web address', function () {
        $post = saveBlogPost(['translations' => blogThreeLanguages(), 'sources' => [
            new BlogSourceData('  Regulation (EU) 2023/988  ', 'https://eur-lex.europa.eu/eli/reg/2023/988/oj'),
            new BlogSourceData('', 'https://example.com'),
            new BlogSourceData('Bad link', 'javascript:alert(1)'),
            new BlogSourceData('Internal path', '/pricing'),
        ]]);

        expect($post->fresh()->sources)->toBe([
            ['label' => 'Regulation (EU) 2023/988', 'url' => 'https://eur-lex.europa.eu/eli/reg/2023/988/oj'],
        ]);

        expect(saveBlogPost([...blogPostAsInput($post), 'sources' => []])->fresh()->sources)->toBeNull();
    });
});

describe('editing a published post', function () {
    it('dates a change of substance, and nothing else', function () {
        $post = BlogPost::factory()->published()->create();
        $input = blogPostAsInput($post);

        saveBlogPost($input);
        expect($post->fresh()->content_updated_at)->toBeNull();

        $input['translations']['fr'] = new BlogPostTranslationData(...[...get_object_vars($input['translations']['fr']), 'metaDescription' => 'Nouvelle description']);
        saveBlogPost($input);
        expect($post->fresh()->content_updated_at)->toBeNull();

        CarbonImmutable::setTestNow('2026-12-01 10:00:00');
        $input['translations']['fr'] = new BlogPostTranslationData(...[...get_object_vars($input['translations']['fr']), 'content' => blogContent(blogContentParagraph('Contenu réécrit.'))]);
        saveBlogPost($input);

        expect($post->fresh()->content_updated_at?->toDateTimeString())->toBe('2026-12-01 10:00:00');

        CarbonImmutable::setTestNow();
    });

    it('does not date the changes made to a draft', function () {
        $post = saveBlogPost(['translations' => blogThreeLanguages()]);

        saveBlogPost([...blogPostAsInput($post), 'translations' => blogThreeLanguages(' revised')]);

        expect($post->fresh()->content_updated_at)->toBeNull();
    });

    it('cannot be saved with a language emptied, and stays as it was', function (string $state) {
        $post = BlogPost::factory()->{$state}()->create();
        $input = blogPostAsInput($post);
        $title = $post->translations->firstWhere('locale', 'es')->title;
        $input['translations']['es'] = new BlogPostTranslationData;

        expect(fn () => saveBlogPost($input))->toThrow(BlogPostException::class, 'cannot be saved incomplete');

        expect($post->translations()->where('locale', 'es')->value('title'))->toBe($title);
    })->with(['published', 'scheduled']);

    it('redirects the old address when its slug changes', function () {
        $post = BlogPost::factory()->published()->create();
        $input = blogPostAsInput($post);
        $oldSlug = $input['translations']['fr']->slug;
        $input['translations']['fr'] = new BlogPostTranslationData(...[...get_object_vars($input['translations']['fr']), 'slug' => 'nouvelle-adresse']);

        saveBlogPost($input);

        $resolution = app(BlogPostSlugLookupService::class)->resolve('fr', $oldSlug);

        expect($resolution->outcome)->toBe(BlogSlugOutcome::Moved)
            ->and($resolution->currentSlug)->toBe('nouvelle-adresse');
    });

    it('lets a published post change its category and keep everything else', function () {
        $post = BlogPost::factory()->published()->create();
        $category = BlogCategory::factory()->withTranslations()->create();

        $saved = saveBlogPost([...blogPostAsInput($post), 'categoryId' => $category->id]);

        expect($saved->blog_category_id)->toBe($category->id)
            ->and($saved->status)->toBe(BlogPostStatus::Published);
    });
});
