<?php

use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogContent;

use function Pest\Laravel\from;
use function Pest\Laravel\get;

/*
 | Blog et langues : un article ou une categorie a un slug propre a chaque langue. La page declare donc
 | elle-meme ses equivalents (hreflang, selecteur de langue), et le segment de categorie est traduit.
 */

uses(RefreshDatabase::class);

it('gives the blog one address per language, with a translated category segment', function () {
    expect(localized_route('blog.index', [], 'en'))->toBe(url('/blog'))
        ->and(localized_route('blog.index', [], 'fr'))->toBe(url('/fr/blog'))
        ->and(localized_route('blog.show', ['slug' => 'x'], 'es'))->toBe(url('/es/blog/x'))
        ->and(localized_route('blog.category', ['slug' => 'x'], 'en'))->toBe(url('/blog/category/x'))
        ->and(localized_route('blog.category', ['slug' => 'x'], 'fr'))->toBe(url('/fr/blog/categorie/x'))
        ->and(localized_route('blog.category', ['slug' => 'x'], 'es'))->toBe(url('/es/blog/categoria/x'));
});

it('declares the article of each language with its own slug', function (string $locale) {
    BlogContent::published('guide');

    $response = get(localized_route('blog.show', ['slug' => "guide-{$locale}"], $locale))->assertOk();

    $response->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<link rel="canonical" href="'.localized_route('blog.show', ['slug' => "guide-{$locale}"], $locale).'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/blog/guide-en').'">', false);

    foreach (BlogContent::LOCALES as $alternate) {
        $response->assertSee('<link rel="alternate" hreflang="'.$alternate.'" href="'.localized_route('blog.show', ['slug' => "guide-{$alternate}"], $alternate).'">', false);
    }

    // Le slug d'une autre langue n'existe pas dans celle-ci.
    $other = $locale === 'en' ? 'fr' : 'en';
    get(localized_route('blog.show', ['slug' => "guide-{$other}"], $locale))->assertNotFound();
})->with(BlogContent::LOCALES);

it('points the language switcher of an article to the article in each language', function () {
    BlogContent::published('guide');

    get('/fr/blog/guide-fr')->assertOk()
        ->assertSee('href="'.url('/blog/guide-en').'"', false)
        ->assertSee('href="'.url('/es/blog/guide-es').'"', false)
        ->assertDontSee('/language/', false);
});

it('announces no equivalent on an error page raised inside the blog, and sends the switcher to the hub', function () {
    BlogContent::published('guide');

    get('/fr/blog/inconnu')->assertNotFound()
        ->assertDontSee('hreflang', false)
        ->assertSee('href="'.url('/blog').'"', false)
        ->assertSee('href="'.url('/es/blog').'"', false)
        ->assertSee('<html lang="fr">', false);
});

it('does not carry the declared equivalents over to the next page', function () {
    BlogContent::published('guide');

    get('/fr/blog/guide-fr')->assertOk();

    get('/fr/pricing')->assertOk()
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/pricing').'">', false)
        ->assertDontSee('guide-en', false);
});

it('falls back to the blog hub when an article address cannot be transposed into another language', function () {
    BlogContent::published('guide');
    $urls = app(LocalizedUrlGenerator::class);

    expect($urls->translate(url('/blog/guide-en'), 'fr'))->toBe(url('/fr/blog'))
        ->and($urls->translate(url('/fr/blog/categorie/x'), 'es'))->toBe(url('/es/blog'))
        ->and($urls->translate(url('/blog?page=2'), 'fr'))->toBe(url('/fr/blog').'?page=2')
        ->and($urls->translate(url('/pricing'), 'es'))->toBe(url('/es/pricing'));

    from(url('/blog/guide-en'))->get('/language/fr')->assertRedirect(url('/fr/blog'));
});
