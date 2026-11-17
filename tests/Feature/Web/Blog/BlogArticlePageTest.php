<?php

use App\Actions\Blog\Post\DeleteBlogPostAction;
use App\Actions\Blog\Post\UnpublishBlogPostAction;
use App\Models\BlogPostRetiredSlug;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogAi;
use Tests\Support\BlogContent;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('shows the article with its author, dates, cover, sources and call to action', function (string $locale) {
    $category = BlogContent::category('rules');
    $post = BlogContent::published('label', $category, CarbonImmutable::parse('2026-11-02 09:00:00'));
    $post->update([
        'content_updated_at' => CarbonImmutable::parse('2026-11-10 08:00:00'),
        'sources' => [['label' => 'Regulation (EU) 2023/988', 'url' => 'https://eur-lex.europa.eu/eli/reg/2023/988/oj']],
    ]);

    $response = get(localized_route('blog.show', ['slug' => "label-{$locale}"], $locale))->assertOk();

    $response->assertSee('<h1 class="article-hero__title">Label '.$locale.'</h1>', false)
        ->assertSee("<p>Body of label in {$locale}.</p>", false)
        ->assertSee('Laëtitia Redonet')
        ->assertSee(CarbonImmutable::parse('2026-11-02')->locale($locale)->isoFormat('LL'))
        ->assertSee(CarbonImmutable::parse('2026-11-10')->locale($locale)->isoFormat('LL'))
        ->assertSee('Rules '.$locale)
        ->assertSee('fetchpriority="high"', false)
        // Les marges de la page sont deduites : un telephone charge la variante 768 px, pas la 1200.
        ->assertSee('sizes="(min-width: 1280px) 972px, (min-width: 400px) 90vw, calc(100vw - 40px)"', false)
        ->assertSee('Regulation (EU) 2023/988')
        ->assertSee('href="https://eur-lex.europa.eu/eli/reg/2023/988/oj" target="_blank" rel="noopener"', false)
        ->assertSee('href="'.localized_route('home', [], $locale).'#quiz"', false)
        ->assertSee('href="'.localized_route('pricing', [], $locale).'"', false);

    expect(substr_count($response->getContent(), '<h1'))->toBe(1);

    // Bandeau compact : l'auteur est nomme sans lien ni qualite (elle reste dans les donnees enrichies),
    // et la categorie n'est plus repetee au-dessus du titre (elle est dans le fil d'Ariane).
    preg_match('/<header class="article-hero">(.*?)<\/header>/s', $response->getContent(), $hero);

    expect($hero[1])->toContain('Laëtitia Redonet')
        ->toContain('<time datetime="2026-11-02">')
        ->toContain(__('Updated on', [], $locale))
        ->not->toContain(config('blog.author.job_title')[$locale])
        ->not->toContain('rel="author"')
        ->not->toContain('class="eyebrow');
})->with(BlogContent::LOCALES);

it('declares the article to search engines and social networks', function () {
    $post = BlogContent::published('label', BlogContent::category('rules'), CarbonImmutable::parse('2026-11-02 09:00:00'));
    $image = $post->featuredImage;
    $shareUrl = url("/storage/blog/{$image->uuid}/{$image->basename}-share.jpg");

    $response = get('/blog/label-en')->assertOk();

    $response->assertSee('<title>Label en · Festilaw</title>', false)
        ->assertSee('<meta name="description" content="Excerpt of label in en.">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:image" content="'.$shareUrl.'">', false)
        ->assertSee('<meta property="article:published_time" content="2026-11-02T09:00:00+00:00">', false);

    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $nodes = collect(json_decode($matches[1], true)['@graph']);
    $article = $nodes->firstWhere('@type', 'BlogPosting');

    expect($article['headline'])->toBe('Label en')
        ->and($article['image'])->toBe([$shareUrl])
        ->and($article['datePublished'])->toBe('2026-11-02T09:00:00+00:00')
        ->and($article['dateModified'])->toBe('2026-11-02T09:00:00+00:00')
        ->and($article['inLanguage'])->toBe('en')
        ->and($article['articleSection'])->toBe('Rules en')
        ->and($article['mainEntityOfPage']['@id'])->toBe(url('/blog/label-en'))
        // L'auteur est nomme avec sa qualite, sans adresse : aucune page du site ne le presente.
        ->and($article['author'])->toBe(['@type' => 'Person', 'name' => 'Laëtitia Redonet', 'jobTitle' => 'EU Market Access & Compliance Advisor'])
        ->and($article['publisher'])->toBe(['@id' => url('/').'#organization'])
        ->and($nodes->firstWhere('@type', 'BreadcrumbList')['itemListElement'])->toHaveCount(4);
});

it('uses the search title and description written for the article when there are some', function () {
    $post = BlogContent::published('label');
    $post->translations()->where('locale', 'en')->update(['meta_title' => 'A title for Google', 'meta_description' => 'A description for Google.']);

    get('/blog/label-en')->assertOk()
        ->assertSee('<title>A title for Google</title>', false)
        ->assertSee('<meta name="description" content="A description for Google.">', false);
});

it('shows the related posts chosen by hand, in their order, else the latest of the category', function () {
    $category = BlogContent::category('rules');
    $post = BlogContent::published('main', $category, CarbonImmutable::parse('2026-11-05'));
    $sibling = BlogContent::published('sibling', $category, CarbonImmutable::parse('2026-11-04'));
    $chosenA = BlogContent::published('alpha', null, CarbonImmutable::parse('2026-11-03'));
    $chosenB = BlogContent::published('beta', null, CarbonImmutable::parse('2026-11-02'));
    $hidden = BlogContent::draft('hidden', $category);

    // Sans choix : les plus recents de la categorie, sans l'article lui-meme ni les brouillons.
    get('/fr/blog/main-fr')->assertOk()
        ->assertSee('Sibling fr')
        ->assertDontSee('Alpha fr')
        ->assertDontSee('Hidden fr');
    expect(substr_count(get('/fr/blog/main-fr')->getContent(), 'class="post-card"'))->toBe(1);

    $post->relatedPosts()->attach([$chosenB->id => ['position' => 0], $chosenA->id => ['position' => 1], $hidden->id => ['position' => 2]]);

    get('/fr/blog/main-fr')->assertOk()
        ->assertSeeInOrder(['article-related', 'Beta fr', 'Alpha fr'], false)
        ->assertDontSee('Sibling fr')
        ->assertDontSee('Hidden fr');
});

it('never shows a draft or a scheduled post, and rejects a malformed address without looking it up', function () {
    BlogContent::draft('pending');
    BlogContent::scheduled('later');
    BlogContent::published('label');

    get('/blog/pending-en')->assertNotFound();
    get('/blog/later-en')->assertNotFound();
    get('/blog/Label-en')->assertNotFound();
    get('/blog/category')->assertNotFound();
});

it('redirects an old address to the current one, permanently and in the same language', function () {
    $post = BlogContent::published('label');
    BlogPostRetiredSlug::factory()->create(['blog_post_id' => $post->id, 'locale' => 'es', 'slug' => 'titulo-antiguo']);

    get('/es/blog/titulo-antiguo')->assertRedirect(url('/es/blog/label-es'))->assertStatus(301);
    get('/blog/titulo-antiguo')->assertNotFound();
});

it('stops redirecting once the post is unpublished', function () {
    $post = BlogContent::published('label');
    BlogPostRetiredSlug::factory()->create(['blog_post_id' => $post->id, 'locale' => 'en', 'slug' => 'old-title']);

    app(UnpublishBlogPostAction::class)->execute($post->id);

    get('/blog/old-title')->assertNotFound();
    get('/blog/label-en')->assertNotFound();
});

it('answers gone, in the language of the address, for a published post that was deleted', function () {
    $post = BlogContent::published('label');

    app(DeleteBlogPostAction::class)->execute($post->id);

    get('/fr/blog/label-fr')->assertStatus(410)
        ->assertSee(__('This page no longer exists', [], 'fr'))
        ->assertSee('<html lang="fr">', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('hreflang', false);

    get('/blog/label-en')->assertStatus(410)->assertSee('This page no longer exists');
});

it('shows the transparency notice of the page language under a generated article only, when the settings have one', function () {
    BlogAi::saveSettings(['transparency_notice' => ['en' => 'Prepared with the help of an AI tool and reviewed by the author.', 'fr' => 'Article préparé avec l\'aide d\'un outil d\'IA et relu par l\'autrice.', 'es' => '']]);
    $generated = BlogContent::published('machine');
    $generated->update(['origin' => 'ai']);
    BlogContent::published('handmade');

    get('/blog/machine-en')->assertSuccessful()->assertSee('Prepared with the help of an AI tool and reviewed by the author.');
    get('/fr/blog/machine-fr')->assertSuccessful()->assertSee('Article préparé avec l\'aide d\'un outil d\'IA et relu par l\'autrice.');
    // Mention vide dans la langue : rien n'est affiche.
    get('/es/blog/machine-es')->assertSuccessful()->assertDontSee('data-transparency-notice', false);
    get('/blog/handmade-en')->assertSuccessful()->assertDontSee('data-transparency-notice', false);
});
