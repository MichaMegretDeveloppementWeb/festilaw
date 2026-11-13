<?php

use App\Models\BlogPost;
use App\Services\Web\Blog\BlogArticlePageService;
use App\Services\Web\Blog\BlogCategoryPageService;
use App\Services\Web\Blog\BlogHomeBlockService;
use App\Services\Web\Blog\BlogHubPageService;
use App\Services\Web\Blog\BlogNavigationService;
use App\Services\Web\Blog\BlogSitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BlogContent;

use function Pest\Laravel\get;

/*
 | Budget de requetes des ecrans publics du blog : le nombre de requetes est celui du plan declare dans
 | chaque service de page, ni plus ni moins, et il ne depend pas du nombre d'articles (300 ici).
 */

uses(RefreshDatabase::class);

/** Nombre de requetes SQL executees par $callback. */
function blogQueryCount(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();

        return count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

beforeEach(function () {
    $this->rules = BlogContent::category('rules');
    $this->platforms = BlogContent::category('platforms', position: 1);
    $this->postIds = BlogContent::seedPublished(300, $this->rules, $this->platforms);
});

it('reads the hub in 4 queries, on the first page as on a later one', function (int $page) {
    $service = app(BlogHubPageService::class);

    expect(blogQueryCount(fn () => $service->page('fr', $page)))->toBe(4);
})->with([1, 2, 25]);

it('reads the hub in a single query while nothing is published', function () {
    BlogPost::query()->update(['status' => 'draft', 'published_at' => null]);

    expect(blogQueryCount(fn () => app(BlogHubPageService::class)->page('en', 1)))->toBe(1);
});

it('reads a category page in 6 queries', function (int $page) {
    $service = app(BlogCategoryPageService::class);

    expect(blogQueryCount(fn () => $service->page('es', 'rules-es', $page)))->toBe(6);
})->with([1, 13]);

it('reads an article in 6 queries when its related posts come from its category', function () {
    $service = app(BlogArticlePageService::class);
    $page = null;

    expect(blogQueryCount(function () use ($service, &$page) {
        $page = $service->page('fr', 'bulk-150-fr');
    }))->toBe(6)
        ->and($page->related)->toHaveCount(3);
});

it('reads an article in 5 queries when its related posts were chosen by hand', function () {
    BlogPost::query()->findOrFail($this->postIds[150])->relatedPosts()->attach([
        $this->postIds[10] => ['position' => 0],
        $this->postIds[20] => ['position' => 1],
        $this->postIds[30] => ['position' => 2],
    ]);
    $service = app(BlogArticlePageService::class);
    $page = null;

    expect(blogQueryCount(function () use ($service, &$page) {
        $page = $service->page('fr', 'bulk-150-fr');
    }))->toBe(5)
        ->and(array_map(fn ($card) => $card->title, $page->related))->toBe(['Bulk 10 fr', 'Bulk 20 fr', 'Bulk 30 fr']);
});

it('reads the home block in 2 queries, and in 1 while nothing is published', function () {
    expect(blogQueryCount(fn () => app(BlogHomeBlockService::class)->latest('en')))->toBe(2);

    BlogPost::query()->update(['status' => 'draft', 'published_at' => null]);

    expect(blogQueryCount(fn () => app(BlogHomeBlockService::class)->latest('en')))->toBe(1);
});

it('checks the blog link once per request, whatever the number of places that show it', function () {
    $count = blogQueryCount(function () {
        app(BlogNavigationService::class)->hasPublishedPosts();
        app(BlogNavigationService::class)->hasPublishedPosts();
        app(BlogNavigationService::class)->hasPublishedPosts();
    });

    expect($count)->toBe(1);
});

it('reads the blog part of the sitemap in 2 queries', function () {
    $entries = [];

    expect(blogQueryCount(function () use (&$entries) {
        $entries = app(BlogSitemapService::class)->entries();
    }))->toBe(2)
        // Le hub, 2 categories et 300 articles.
        ->and($entries)->toHaveCount(303);
});

it('serves the whole pages within their plan plus the single blog link check', function (string $url, int $queries) {
    $response = null;

    expect(blogQueryCount(function () use ($url, &$response) {
        $response = get($url);
    }))->toBe($queries);

    $response->assertOk();
})->with([
    'hub' => ['/blog', 5],
    'hub, page 2' => ['/fr/blog?page=2', 5],
    'category' => ['/es/blog/categoria/platforms-es', 7],
    'article' => ['/blog/bulk-42-en', 7],
    'sitemap' => ['/sitemap.xml', 2],
]);
