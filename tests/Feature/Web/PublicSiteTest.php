<?php

use function Pest\Laravel\get;

/*
 | Couverture du site public : rendu des pages et SEO (canonical, JSON-LD, sitemap, robots, fil
 | d'Ariane, FAQ). Un seul jeu d'URLs, langue canonique anglaise : pas de prefixe de langue ni de
 | hreflang (la traduction FR/ES est visuelle, cf. LocaleTest). Pas de base de donnees : GET sur vues.
 */

dataset('indexable_pages', [
    'home' => ['home', 'Your GPSR'],
    'about' => ['about', 'Built by entrepreneurs'],
    'understand-gpsr' => ['understand-gpsr', 'Understanding the'],
    'services' => ['services', 'GPSR service'],
    'pricing' => ['pricing', 'no surprises'],
    'excluded-products' => ['excluded-products', 'Products we'],
    'contact' => ['contact', 'compliance'],
]);

it('serves each public page with 200', function (string $routeName, string $needle) {
    get(route($routeName))
        ->assertOk()
        ->assertSee($needle, false);
})->with('indexable_pages');

it('serves the root as the home page (no locale redirect)', function () {
    get('/')
        ->assertOk()
        ->assertSee('Your GPSR', false);
});

it('returns 404 for an unknown path', function () {
    get('/does-not-exist')->assertNotFound();
});

it('links navigation to the real pages, including About', function () {
    get(route('home'))
        ->assertSee(route('about'), false)
        ->assertSee(route('understand-gpsr'), false)
        ->assertSee(route('services'), false)
        ->assertSee(route('pricing'), false);
});

it('sets a self-referencing canonical', function () {
    get(route('about'))
        ->assertOk()
        ->assertSee('<link rel="canonical"', false);
});

it('marks marketing pages as indexable', function () {
    get(route('home'))
        ->assertOk()
        ->assertSee('name="robots" content="index, follow"', false);
});

it('emits no hreflang (single-language site, visual translation only)', function () {
    get(route('about'))
        ->assertOk()
        ->assertDontSee('hreflang', false);
});

it('includes global and page-specific structured data', function () {
    get(route('pricing'))
        ->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('"Organization"', false)
        ->assertSee('"WebSite"', false)
        ->assertSee('"Service"', false)
        ->assertSee('"Offer"', false)
        ->assertSee('"BreadcrumbList"', false);
});

it('shows breadcrumbs on sub-pages but not on home or contact', function () {
    get(route('about'))->assertSee('aria-label="Breadcrumb"', false);
    get(route('home'))->assertDontSee('aria-label="Breadcrumb"', false);
    get(route('contact'))->assertDontSee('aria-label="Breadcrumb"', false);
});

it('renders the FAQ with FAQPage markup on Understand GPSR and Pricing', function () {
    get(route('understand-gpsr'))
        ->assertOk()
        ->assertSee('class="faq"', false)
        ->assertSee('"FAQPage"', false)
        ->assertSee('What is the GPSR?');

    get(route('pricing'))
        ->assertOk()
        ->assertSee('"FAQPage"', false)
        ->assertSee('How does Festilaw work?');
});

it('serves a valid XML sitemap of the indexable pages', function () {
    $response = get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('xml');

    $response->assertSee(route('home'), false)
        ->assertSee(route('pricing'), false)
        ->assertDontSee('/get-started', false);
});

it('serves robots.txt referencing the sitemap', function () {
    get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap:', false)
        ->assertSee('sitemap.xml', false);
});
