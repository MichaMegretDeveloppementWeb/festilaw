<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\Support\BlogContent;

use function Pest\Laravel\get;

/*
 | Le sitemap decoupe comme celui d'un site WordPress : un index (l'adresse declaree a la Search Console
 | et dans robots.txt), puis un fichier pour les pages fixes, un pour les articles et un pour les
 | categories. Le contenu de chaque fichier est verifie dans BlogSitemapTest et PublicSiteTest.
 */

uses(RefreshDatabase::class);

/**
 * L'index lu comme un robot : adresse du fichier => date de modification.
 *
 * @return array<string, ?string>
 */
function sitemapIndexFiles(TestResponse $response): array
{
    $files = [];

    foreach (simplexml_load_string($response->getContent())->sitemap as $sitemap) {
        $files[(string) $sitemap->loc] = isset($sitemap->lastmod) ? (string) $sitemap->lastmod : null;
    }

    return $files;
}

it('lists only the fixed pages in the index while nothing is published', function () {
    BlogContent::draft('pending', BlogContent::category('rules'));

    expect(sitemapIndexFiles(get('/sitemap.xml')->assertOk()))->toBe([url('/sitemap-pages.xml') => null]);
});

it('adds the posts and categories files to the index, dated by the latest change, from the first post', function () {
    $label = BlogContent::published('label', BlogContent::category('rules'), CarbonImmutable::parse('2026-11-02 09:00:00'));
    BlogContent::published('older', null, CarbonImmutable::parse('2026-10-01 09:00:00'));
    $label->update(['content_updated_at' => CarbonImmutable::parse('2026-11-10 08:00:00')]);

    expect(sitemapIndexFiles(get('/sitemap.xml')->assertOk()))->toBe([
        url('/sitemap-pages.xml') => '2026-11-10T08:00:00+00:00',
        url('/sitemap-posts.xml') => '2026-11-10T08:00:00+00:00',
        url('/sitemap-categories.xml') => '2026-11-10T08:00:00+00:00',
    ]);
});

it('leaves the categories file out of the index while no published post has a category', function () {
    BlogContent::published('loose')->update(['blog_category_id' => null]);

    expect(array_keys(sitemapIndexFiles(get('/sitemap.xml')->assertOk())))->toBe([
        url('/sitemap-pages.xml'),
        url('/sitemap-posts.xml'),
    ]);
});

it('serves each file as XML for robots, kept out of search results, with a stylesheet for people', function (string $path) {
    BlogContent::published('label', BlogContent::category('rules'));

    $response = get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    $stylesheet = Vite::asset('resources/css/web/sitemap/index.css');

    expect($response->headers->get('Content-Type'))->toBe('application/xml; charset=UTF-8')
        // La declaration XML ouvre le fichier, sans rien avant, sinon le fichier n'est plus du XML valide.
        ->and($response->getContent())->toStartWith('<?xml version="1.0" encoding="UTF-8"?>'."\n".'<?xml-stylesheet type="text/css" href="'.$stylesheet.'"?>')
        ->and(simplexml_load_string($response->getContent()))->not->toBeFalse();
})->with(['/sitemap.xml', '/sitemap-pages.xml', '/sitemap-posts.xml', '/sitemap-categories.xml']);
