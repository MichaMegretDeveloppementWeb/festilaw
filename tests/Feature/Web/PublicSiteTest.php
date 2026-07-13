<?php

use function Pest\Laravel\get;

/*
 | Couverture du site public : routing localise, rendu des pages, et SEO
 | (canonical, hreflang, noindex des locales non publiees, JSON-LD, sitemap, robots, fil d'Ariane, FAQ).
 | Pas de base de donnees : ce sont des GET sur des vues statiques.
 */

dataset('indexable_pages', [
    'home' => ['home', 'Your GPSR'],
    'about' => ['about', 'Built by entrepreneurs'],
    'understand-gpsr' => ['understand-gpsr', 'Understanding the'],
    'services' => ['services', 'GPSR service'],
    'pricing' => ['pricing', 'no surprises'],
    'excluded-products' => ['excluded-products', "don't cover"],
    'contact' => ['contact', 'compliance'],
]);

it('serves each public page with 200 in English', function (string $routeName, string $needle) {
    get(route($routeName, ['locale' => 'en']))
        ->assertOk()
        ->assertSee($needle, false);
})->with('indexable_pages');

it('redirects the root to a locale', function () {
    get('/')->assertRedirect();
});

it('returns 404 for an unsupported locale', function () {
    get('/xx')->assertNotFound();
});

it('links navigation to the real pages, including About', function () {
    get(route('home', ['locale' => 'en']))
        ->assertSee(route('about', ['locale' => 'en']), false)
        ->assertSee(route('understand-gpsr', ['locale' => 'en']), false)
        ->assertSee(route('services', ['locale' => 'en']), false)
        ->assertSee(route('pricing', ['locale' => 'en']), false);
});

it('sets a self-referencing canonical', function () {
    get(route('about', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('<link rel="canonical"', false);
});

it('indexes published locales and noindexes the rest', function () {
    get(route('home', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('name="robots" content="index, follow"', false);

    get(route('home', ['locale' => 'fr']))
        ->assertOk()
        ->assertSee('name="robots" content="noindex, follow"', false);
});

it('emits hreflang only for published locales plus x-default', function () {
    get(route('about', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('rel="alternate" hreflang="en"', false)
        ->assertSee('rel="alternate" hreflang="x-default"', false)
        ->assertDontSee('rel="alternate" hreflang="fr"', false);
});

it('includes global and page-specific structured data', function () {
    get(route('pricing', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('"Organization"', false)
        ->assertSee('"WebSite"', false)
        ->assertSee('"Service"', false)
        ->assertSee('"Offer"', false)
        ->assertSee('"BreadcrumbList"', false);
});

it('shows breadcrumbs on sub-pages but not on home or contact', function () {
    get(route('about', ['locale' => 'en']))->assertSee('aria-label="Breadcrumb"', false);
    get(route('home', ['locale' => 'en']))->assertDontSee('aria-label="Breadcrumb"', false);
    get(route('contact', ['locale' => 'en']))->assertDontSee('aria-label="Breadcrumb"', false);
});

it('renders the FAQ with FAQPage markup on Understand GPSR and Pricing', function () {
    get(route('understand-gpsr', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('class="faq"', false)
        ->assertSee('"FAQPage"', false)
        ->assertSee('What is the GPSR?');

    get(route('pricing', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('"FAQPage"', false)
        ->assertSee('How does Festilaw work?');
});

it('serves a valid XML sitemap of published pages only', function () {
    $response = get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('xml');

    $response->assertSee(route('home', ['locale' => 'en']), false)
        ->assertSee(route('pricing', ['locale' => 'en']), false)
        ->assertDontSee('/fr/', false);
});

it('serves robots.txt referencing the sitemap', function () {
    get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap:', false)
        ->assertSee('sitemap.xml', false);
});
