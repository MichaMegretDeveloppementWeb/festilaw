<?php

use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

/*
 | Generateur d'adresses des pages publiques (une adresse par langue) et helper localized_route().
 */

// Les pages publiques lisent la base : lien "Blog" de l'en-tete et du pied de page, bloc de l'accueil.
uses(RefreshDatabase::class);

it('generates the unprefixed address in the default language and a prefixed one in the others', function (string $locale, string $pricingPath, string $homePath) {
    app()->setLocale($locale);

    expect(localized_route('pricing'))->toBe(url($pricingPath))
        ->and(localized_route('home'))->toBe(url($homePath));
})->with([
    'en' => ['en', '/pricing', '/'],
    'fr' => ['fr', '/fr/pricing', '/fr'],
    'es' => ['es', '/es/pricing', '/es'],
]);

it('generates the address of an explicit language whatever the current one', function () {
    app()->setLocale('fr');

    expect(localized_route('about', [], 'es'))->toBe(url('/es/about'))
        ->and(localized_route('about', [], 'en'))->toBe(url('/about'));
});

it('falls back to the default language for an unsupported one', function () {
    app()->setLocale('de');

    expect(localized_route('pricing'))->toBe(url('/pricing'))
        ->and(localized_route('pricing', [], 'it'))->toBe(url('/pricing'));
});

it('leaves a page without an address per language untouched', function () {
    app()->setLocale('fr');

    expect(localized_route('get-started.index'))->toBe(route('get-started.index'))
        ->and(localized_route('find-my-project'))->toBe(route('find-my-project'));
});

it('lists the same page in every language', function () {
    expect(app(LocalizedUrlGenerator::class)->inAllLocales('contact'))->toBe([
        'en' => url('/contact'),
        'fr' => url('/fr/contact'),
        'es' => url('/es/contact'),
    ]);
});

it('recognises the current public page in any language', function () {
    get('/fr/pricing')->assertOk();

    $localizedUrls = app(LocalizedUrlGenerator::class);

    expect($localizedUrls->isCurrent('pricing'))->toBeTrue()
        ->and($localizedUrls->isCurrent('home', 'pricing'))->toBeTrue()
        ->and($localizedUrls->isCurrent('home', 'about'))->toBeFalse();
});

it('gives the alternates of a public page and none for a page without an address per language', function () {
    get('/es/about')->assertOk();

    expect(app(LocalizedUrlGenerator::class)->alternates())->toBe([
        'en' => url('/about'),
        'fr' => url('/fr/about'),
        'es' => url('/es/about'),
    ]);

    get(route('get-started.index'))->assertOk();

    expect(app(LocalizedUrlGenerator::class)->alternates())->toBe([])
        ->and(app(LocalizedUrlGenerator::class)->isCurrent('home'))->toBeFalse();
});

it('gives clean alternates for a page served by a view route', function () {
    // Route::view range le nom de sa vue dans les parametres de la route : il ne doit pas fuir dans l'adresse.
    get('/fr/terms')->assertOk();

    expect(app(LocalizedUrlGenerator::class)->alternates())->toBe([
        'en' => url('/terms'),
        'fr' => url('/fr/terms'),
        'es' => url('/es/terms'),
    ]);
});

it('translates the address of a public page into another language and keeps its query string', function () {
    $localizedUrls = app(LocalizedUrlGenerator::class);

    expect($localizedUrls->translate(url('/fr/pricing?source=ad'), 'es'))->toBe(url('/es/pricing?source=ad'))
        ->and($localizedUrls->translate(url('/pricing'), 'fr'))->toBe(url('/fr/pricing'))
        ->and($localizedUrls->translate(url('/es'), 'en'))->toBe(url('/'));
});

it('does not translate an address that is not a public page', function () {
    $localizedUrls = app(LocalizedUrlGenerator::class);

    expect($localizedUrls->translate(route('get-started.index'), 'fr'))->toBeNull()
        ->and($localizedUrls->translate(url('/this-page-does-not-exist'), 'fr'))->toBeNull()
        ->and($localizedUrls->translate(url('/livewire/update'), 'fr'))->toBeNull();
});

it('lets a page declare its own equivalents, for the hreflang tags and the language switcher', function () {
    get('/fr/pricing')->assertOk();

    $localizedUrls = app(LocalizedUrlGenerator::class);
    $declared = ['en' => url('/blog/label'), 'fr' => url('/fr/blog/etiquette'), 'es' => url('/es/blog/etiqueta')];

    $localizedUrls->declareAlternates($declared);

    expect($localizedUrls->alternates())->toBe($declared)
        ->and($localizedUrls->switcherLinks())->toBe($declared);
});

it('lets a page declare that it has no equivalent, with other links for the language switcher', function () {
    get('/fr/pricing')->assertOk();

    $localizedUrls = app(LocalizedUrlGenerator::class);
    $hubs = $localizedUrls->inAllLocales('blog.index');

    $localizedUrls->declareNoAlternates($hubs);

    expect($localizedUrls->alternates())->toBe([])
        ->and($localizedUrls->switcherLinks())->toBe($hubs);

    // Une declaration d'equivalents remplace la precedente, liens du selecteur compris.
    $declared = ['en' => url('/a'), 'fr' => url('/fr/a'), 'es' => url('/es/a')];
    $localizedUrls->declareAlternates($declared);

    expect($localizedUrls->switcherLinks())->toBe($declared);
});

it('sends a page with a slug to the page named by its route when it is translated', function () {
    $localizedUrls = app(LocalizedUrlGenerator::class);

    // Un slug appartient a une langue : l'adresse ne se reconstruit pas telle quelle dans une autre.
    expect($localizedUrls->translate(url('/blog/some-article'), 'es'))->toBe(url('/es/blog'))
        ->and($localizedUrls->translate(url('/fr/blog/categorie/regles'), 'en'))->toBe(url('/blog'))
        ->and($localizedUrls->translate(url('/fr/blog?page=3'), 'en'))->toBe(url('/blog?page=3'));
});
