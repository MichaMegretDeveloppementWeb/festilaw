<?php

use App\Enums\Submission\SubmissionType;
use App\Services\Billing\PackPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\Support\BlogContent;

use function Pest\Laravel\get;

/*
 | Donnees structurees des pages publiques : un seul graphe par page, ou l'organisation, le site, la page
 | elle-meme, son fil d'Ariane et ses entites se citent par leur identifiant, dans la langue de la page.
 | Les champs propres a un article sont verifies dans BlogArticlePageTest.
 */

uses(RefreshDatabase::class);

/**
 * @return Collection<int, array<string, mixed>>
 */
function structuredGraph(TestResponse $response): Collection
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);

    return collect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR)['@graph']);
}

/**
 * Le titre de la page tel que le lit un visiteur (entites HTML decodees).
 */
function pageTitle(TestResponse $response): string
{
    preg_match('/<title>(.*?)<\/title>/s', $response->getContent(), $matches);

    return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

it('describes each indexable page once, with its type, language, site and breadcrumb', function (string $routeName, string $type, bool $hasBreadcrumb) {
    foreach (BlogContent::LOCALES as $locale) {
        $url = localized_route($routeName, [], $locale);
        $response = get($url)->assertOk();
        $graph = structuredGraph($response);
        $pages = $graph->filter(fn (array $node): bool => str_ends_with($node['@id'] ?? '', '#webpage'))->values();

        expect($pages)->toHaveCount(1)
            ->and($pages[0]['@type'])->toBe($type)
            ->and($pages[0]['@id'])->toBe($url.'#webpage')
            ->and($pages[0]['url'])->toBe($url)
            ->and($pages[0]['name'])->toBe(pageTitle($response))
            ->and($pages[0]['inLanguage'])->toBe($locale)
            ->and($pages[0]['isPartOf'])->toBe(['@id' => url('/').'#website']);

        if ($hasBreadcrumb) {
            expect($pages[0]['breadcrumb'])->toBe(['@id' => $url.'#breadcrumb'])
                ->and($graph->firstWhere('@type', 'BreadcrumbList')['@id'])->toBe($url.'#breadcrumb');
        } else {
            // Pas de fil d'Ariane affiche sur la page, donc aucun dans ses donnees.
            expect($pages[0])->not->toHaveKey('breadcrumb')
                ->and($graph->firstWhere('@type', 'BreadcrumbList'))->toBeNull();
        }
    }
})->with([
    'home' => ['home', 'WebPage', false],
    'about' => ['about', 'AboutPage', true],
    'understand GPSR' => ['understand-gpsr', 'FAQPage', true],
    'services' => ['services', 'WebPage', true],
    'pricing' => ['pricing', 'FAQPage', true],
    'excluded products' => ['excluded-products', 'WebPage', true],
    'contact' => ['contact', 'ContactPage', false],
    'legal notice' => ['legal-notice', 'WebPage', true],
    'privacy policy' => ['privacy-policy', 'WebPage', true],
    'terms' => ['terms', 'WebPage', true],
]);

it('links the site to Festilaw, declared first with its legal identity', function () {
    $graph = structuredGraph(get(localized_route('about', [], 'fr'))->assertOk());

    expect($graph[0]['@id'])->toBe(url('/').'#organization')
        ->and($graph[0]['legalName'])->toBe('Festilaw B.V.')
        ->and($graph->firstWhere('@type', 'WebSite'))->toBe([
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => config('app.name'),
            'url' => url('/'),
            'inLanguage' => ['en', 'fr', 'es'],
            'publisher' => ['@id' => url('/').'#organization'],
        ])
        ->and($graph->firstWhere('@type', 'AboutPage')['about'])->toBe(['@id' => url('/').'#organization']);
});

it('puts the questions shown on Understand GPSR on its single page node, in the language of the page', function () {
    $url = localized_route('understand-gpsr', [], 'fr');
    $graph = structuredGraph(get($url)->assertOk());
    $page = $graph->firstWhere('@type', 'FAQPage');

    expect($graph->where('url', $url))->toHaveCount(1)
        ->and($page['mainEntity'][0]['@type'])->toBe('Question')
        ->and($page['mainEntity'][0]['name'])->toBe(__('What is the GPSR?', [], 'fr'))
        ->and($page['mainEntity'][0]['name'])->not->toBe('What is the GPSR?');
});

it('describes the service of Festilaw on Services, and its yearly offers on Pricing', function () {
    $services = structuredGraph(get(localized_route('services', [], 'es'))->assertOk());
    $pricing = structuredGraph(get(localized_route('pricing', [], 'es'))->assertOk());
    $service = $pricing->firstWhere('@type', 'Service');
    $creatorPrice = number_format(app(PackPricingService::class)->annualCents(SubmissionType::Starter) / 100, 2, '.', '');

    expect($services->firstWhere('@type', 'WebPage')['mainEntity'])->toBe(['@id' => url('/').'#service'])
        ->and($services->firstWhere('@type', 'Service'))->not->toHaveKey('offers')
        ->and($service['@id'])->toBe(url('/').'#service')
        ->and($service['provider'])->toBe(['@id' => url('/').'#organization'])
        ->and($service['url'])->toBe(localized_route('services', [], 'es'))
        ->and(array_column($service['offers'], 'name'))->toBe(['Creator Pack', 'Pro Pack'])
        ->and($service['offers'][0]['price'])->toBe($creatorPrice)
        ->and($service['offers'][0]['url'])->toBe(localized_route('pricing', [], 'es'))
        ->and($service['offers'][0]['priceSpecification'])->toBe([
            '@type' => 'UnitPriceSpecification',
            'price' => $creatorPrice,
            'priceCurrency' => 'EUR',
            'unitCode' => 'ANN',
            'unitText' => 'year',
        ]);
});

it('describes the blog of each language on its home, and each article as part of it', function () {
    BlogContent::published('label', BlogContent::category('rules'));

    $hub = structuredGraph(get('/fr/blog')->assertOk());
    $article = structuredGraph(get('/fr/blog/label-fr')->assertOk())->firstWhere('@type', 'BlogPosting');

    expect($hub->firstWhere('@type', 'CollectionPage')['mainEntity'])->toBe(['@id' => url('/fr/blog').'#blog'])
        ->and($hub->firstWhere('@type', 'Blog'))->toMatchArray([
            '@id' => url('/fr/blog').'#blog',
            'url' => url('/fr/blog'),
            'inLanguage' => 'fr',
            'publisher' => ['@id' => url('/').'#organization'],
        ])
        ->and($article['isPartOf'])->toBe(['@id' => url('/fr/blog').'#blog']);
});

it('keeps only the organization and the site on a page kept out of search results', function (string $path) {
    $graph = structuredGraph(get($path));

    expect($graph->pluck('@type')->all())->toBe([['Organization', 'LegalService'], 'WebSite']);
})->with([
    'subscription journey' => ['/get-started'],
    'blog without any post' => ['/blog'],
    'missing page' => ['/fr/blog/nothing-here'],
]);

it('writes the page name as people read it, without HTML entities', function () {
    $post = BlogContent::published('label', BlogContent::category('rules'));
    $post->translations()->where('locale', 'en')->update(['title' => "The seller's labels & lots"]);

    $page = structuredGraph(get('/blog/label-en')->assertOk())->firstWhere('@type', 'WebPage');

    expect($page['name'])->toBe("The seller's labels & lots · Festilaw");
});
