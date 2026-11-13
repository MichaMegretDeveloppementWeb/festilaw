<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogContent;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('shows a waiting message and stays out of the index while nothing is published', function () {
    BlogContent::draft('brouillon');
    BlogContent::scheduled('programme');

    get('/fr/blog')->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee(__('Our first articles are on their way. Come back soon.', [], 'fr'))
        ->assertDontSee('Brouillon fr')
        ->assertDontSee('Programme fr')
        ->assertDontSee('CollectionPage', false);
});

it('lists the published posts of the page language, the latest one featured', function (string $locale) {
    $category = BlogContent::category('rules');
    BlogContent::published('older', $category, CarbonImmutable::parse('2026-10-01 09:00:00'));
    BlogContent::published('newest', $category, CarbonImmutable::parse('2026-11-02 09:00:00'));
    BlogContent::draft('hidden', $category);

    $response = get(localized_route('blog.index', [], $locale))->assertOk();

    $response->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSeeInOrder(['post-featured', 'Newest '.$locale, 'post-grid', 'Older '.$locale], false)
        ->assertSee(localized_route('blog.show', ['slug' => "newest-{$locale}"], $locale), false)
        ->assertSee('Excerpt of older in '.$locale.'.')
        ->assertSee('Rules '.$locale)
        ->assertSee(localized_route('blog.category', ['slug' => "rules-{$locale}"], $locale), false)
        ->assertDontSee('Hidden '.$locale)
        ->assertSee('"CollectionPage"', false)
        ->assertSee('"BreadcrumbList"', false);

    // Date ecrite dans la langue de la page.
    $response->assertSee(CarbonImmutable::parse('2026-11-02')->locale($locale)->isoFormat('LL'));
})->with(BlogContent::LOCALES);

it('puts thirteen posts on the first page and the rest on the following ones', function () {
    BlogContent::seedPublished(14);

    $first = get('/blog')->assertOk();
    expect(substr_count($first->getContent(), 'class="post-card"'))->toBe(12)
        ->and(substr_count($first->getContent(), 'class="post-featured"'))->toBe(1);
    $first->assertSee('Bulk 13 en')->assertDontSee('Bulk 0 en')
        ->assertSee('<link rel="canonical" href="'.url('/blog').'">', false)
        ->assertSee('href="'.url('/blog').'?page=2"', false);

    $second = get('/blog?page=2')->assertOk();
    expect(substr_count($second->getContent(), 'class="post-card"'))->toBe(1)
        ->and(substr_count($second->getContent(), 'class="post-featured"'))->toBe(0);
    $second->assertSee('Bulk 0 en')
        ->assertSee('<link rel="canonical" href="'.url('/blog').'?page=2">', false)
        ->assertSee('<link rel="alternate" hreflang="fr" href="'.url('/fr/blog').'?page=2">', false)
        ->assertSee('<meta property="og:url" content="'.url('/blog').'?page=2">', false);
});

it('answers not found beyond the last page and serves the first page for a page that is not a number', function () {
    BlogContent::seedPublished(14);

    get('/blog?page=3')->assertNotFound()->assertDontSee('hreflang', false);
    get('/blog?page=abc')->assertOk()->assertSee('<link rel="canonical" href="'.url('/blog').'">', false);
    get('/blog?page=1')->assertOk()->assertSee('<link rel="canonical" href="'.url('/blog').'">', false);
});

it('marks the blog as the active section and "all articles" as the active filter', function () {
    BlogContent::published('guide');

    get('/blog')->assertOk()
        ->assertSee('class="site-header__link is-active">'.__('Blog'), false)
        ->assertSee('class="blog-cats__link is-active"  aria-current="page" >'.__('All articles'), false);
});
