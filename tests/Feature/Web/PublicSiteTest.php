<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

/*
 | Couverture du site public : rendu des pages et SEO (canonical, JSON-LD, sitemap, robots, fil
 | d'Ariane, FAQ), ici dans la langue par defaut (anglais, adresses sans prefixe). Les versions /fr et
 | /es et leurs hreflang sont couvertes par LocalizedRoutingTest.
 */

// Les pages publiques lisent la base : lien "Blog" de l'en-tete et du pied de page, bloc de l'accueil.
uses(RefreshDatabase::class);

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

it('declares the other language versions of a marketing page (hreflang)', function () {
    get(route('about'))
        ->assertOk()
        ->assertSee('<link rel="alternate" hreflang="fr" href="'.route('fr.about').'">', false)
        ->assertSee('<link rel="alternate" hreflang="es" href="'.route('es.about').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.route('about').'">', false);
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

it('serves a valid XML sitemap of the indexable pages in every language', function () {
    $response = get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('xml');

    $response->assertSee('<loc>'.route('home').'</loc>', false)
        ->assertSee('<loc>'.route('pricing').'</loc>', false)
        ->assertSee('<loc>'.route('fr.pricing').'</loc>', false)
        ->assertSee('<loc>'.route('es.home').'</loc>', false)
        ->assertDontSee('/get-started', false)
        ->assertDontSee('/legal-notice', false);

    // 7 pages x 3 langues, chacune avec ses equivalents (hreflang) et l'anglais par defaut.
    $alternatesByAddress = [];

    foreach (simplexml_load_string($response->getContent())->url as $url) {
        $alternates = [];

        foreach ($url->children('http://www.w3.org/1999/xhtml')->link as $link) {
            $alternates[(string) $link->attributes()->hreflang] = (string) $link->attributes()->href;
        }

        $alternatesByAddress[(string) $url->loc] = $alternates;
    }

    expect($alternatesByAddress)->toHaveCount(21)
        ->and($alternatesByAddress[route('fr.pricing')])->toBe([
            'en' => route('pricing'),
            'fr' => route('fr.pricing'),
            'es' => route('es.pricing'),
            'x-default' => route('pricing'),
        ]);
});

it('ships a static robots.txt that allows every crawler and references the sitemap', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)
        ->toContain("User-agent: *\nAllow: /")
        ->toContain("User-agent: Claude-User\nAllow: /")
        ->toContain("User-agent: ClaudeBot\nAllow: /")
        ->toContain('Sitemap: https://festilaw.com/sitemap.xml')
        ->not->toContain('Disallow');
});

it('references the favicons and the OG share image in the head', function () {
    get(route('home'))
        ->assertOk()
        ->assertSee('rel="icon"', false)
        ->assertSee('apple-touch-icon.png', false)
        ->assertSee('rel="manifest"', false)
        ->assertSee('og-default.png', false);
});

it('shows the company legal line and the certification in the footer and the trust block, without the placeholder testimonial', function () {
    get(route('home'))
        ->assertOk()
        // Ligne legale du footer (texte fourni par Festilaw le 29/09/2026).
        ->assertSee('Festilaw B.V.', false)
        ->assertSee('Registered office: Nijmegen, the Netherlands', false)
        ->assertSee('KvK 77058720', false)
        ->assertSee('VAT NL860886761B01', false)
        // Mention de certification corrigee (footer + bloc confiance), l'ancienne formulation a disparu.
        ->assertSee('Consumer Law Ready certified', false)
        ->assertDontSee('trained under the European Commission programme', false)
        // Le temoignage fictif de maquette est retire ; le bandeau des places de marche reste, sans pretendre
        // y avoir deja des clients (Festilaw n'en a pas encore sur ces plateformes, 05/10/2026).
        ->assertDontSee('Maya Thornton', false)
        ->assertDontSee('Wildthread', false)
        ->assertSee('Built for sellers on', false)
        ->assertDontSee('Trusted by sellers on', false);
});

it('translates the footer legal line', function () {
    get(route('fr.home'))
        ->assertOk()
        ->assertSee('Siège social : Nijmegen, Pays-Bas', false)
        ->assertSee('TVA NL860886761B01', false)
        ->assertSee('Certification Consumer Law Ready', false);
});

it('keeps administration accessible directly without advertising it in the public footer', function () {
    get(route('home'))->assertOk()->assertDontSee(route('admin.login'), false);
    get(route('admin.login'))->assertOk();
});

it('opens each inner page with its breadcrumb straight above the title, without a label repeating it', function (string $routeName) {
    $content = get(route($routeName))->assertOk()->getContent();

    preg_match('/<section class="page-hero[^"]*">(.*?)<\/section>/s', $content, $hero);

    expect($hero[1])->toContain('class="breadcrumb"')
        ->toContain('<h1 class="page-hero__title">')
        ->not->toContain('page-hero__eyebrow');
})->with(['about', 'understand-gpsr', 'services', 'pricing', 'excluded-products', 'legal-notice', 'privacy-policy', 'terms', 'blog.index']);
