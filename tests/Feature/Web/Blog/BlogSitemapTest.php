<?php

use App\Actions\Blog\Post\UnpublishBlogPostAction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\BlogContent;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Le sitemap lu comme un robot : adresse => [date de modification, equivalents par langue].
 *
 * @return array<string, array{lastmod: ?string, alternates: array<string, string>}>
 */
function blogSitemapEntries(TestResponse $response): array
{
    $entries = [];

    foreach (simplexml_load_string($response->getContent())->url as $url) {
        $alternates = [];

        foreach ($url->children('http://www.w3.org/1999/xhtml')->link as $link) {
            $alternates[(string) $link->attributes()->hreflang] = (string) $link->attributes()->href;
        }

        $entries[(string) $url->loc] = [
            'lastmod' => isset($url->lastmod) ? (string) $url->lastmod : null,
            'alternates' => $alternates,
        ];
    }

    return $entries;
}

it('lists no blog address while nothing is published', function () {
    BlogContent::draft('pending', BlogContent::category('rules'));
    BlogContent::scheduled('later');

    $pages = blogSitemapEntries(get('/sitemap-pages.xml')->assertOk());

    // 10 pages fixes (dont 3 legales) x 3 langues, sans l'accueil du blog.
    expect($pages)->toHaveCount(30)
        ->and(array_filter(array_keys($pages), fn (string $url): bool => str_contains($url, '/blog')))->toBe([])
        ->and(blogSitemapEntries(get('/sitemap-posts.xml')->assertOk()))->toBe([])
        ->and(blogSitemapEntries(get('/sitemap-categories.xml')->assertOk()))->toBe([]);
});

it('lists the hub, the categories with a published post and the published posts, in every language', function () {
    $rules = BlogContent::category('rules');
    BlogContent::category('empty', position: 1);
    $label = BlogContent::published('label', $rules, CarbonImmutable::parse('2026-11-02 09:00:00'));
    BlogContent::published('older', null, CarbonImmutable::parse('2026-10-01 09:00:00'));
    BlogContent::draft('pending', $rules);
    $label->update(['content_updated_at' => CarbonImmutable::parse('2026-11-10 08:00:00')]);
    $older = url('/blog/older-en');

    $pages = blogSitemapEntries(get('/sitemap-pages.xml')->assertOk());
    $categories = blogSitemapEntries(get('/sitemap-categories.xml')->assertOk());
    $posts = blogSitemapEntries(get('/sitemap-posts.xml')->assertOk());

    // L'accueil du blog rejoint les pages fixes. "older" a sa propre categorie (creee par la factory),
    // "empty" n'a aucun article publie. Les articles vont du plus recent au plus ancien.
    expect($pages)->toHaveCount(30 + 3)
        ->and($categories)->toHaveCount(2 * 3)
        ->and(array_keys($categories))->not->toContain(url('/blog/category/empty-en'))
        ->and(array_keys($posts))->toBe([
            url('/blog/label-en'), url('/fr/blog/label-fr'), url('/es/blog/label-es'),
            url('/blog/older-en'), url('/fr/blog/older-fr'), url('/es/blog/older-es'),
        ])
        ->and($pages[url('/fr/blog')])->toBe([
            'lastmod' => '2026-11-10T08:00:00+00:00',
            'alternates' => ['en' => url('/blog'), 'fr' => url('/fr/blog'), 'es' => url('/es/blog'), 'x-default' => url('/blog')],
        ])
        ->and($categories[url('/es/blog/categoria/rules-es')])->toBe([
            'lastmod' => null,
            'alternates' => [
                'en' => url('/blog/category/rules-en'),
                'fr' => url('/fr/blog/categorie/rules-fr'),
                'es' => url('/es/blog/categoria/rules-es'),
                'x-default' => url('/blog/category/rules-en'),
            ],
        ])
        ->and($posts[url('/fr/blog/label-fr')])->toBe([
            'lastmod' => '2026-11-10T08:00:00+00:00',
            'alternates' => [
                'en' => url('/blog/label-en'),
                'fr' => url('/fr/blog/label-fr'),
                'es' => url('/es/blog/label-es'),
                'x-default' => url('/blog/label-en'),
            ],
        ])
        ->and($posts[$older]['lastmod'])->toBe('2026-10-01T09:00:00+00:00');
});

it('drops a post, and its category when it was the last one, as soon as it is unpublished', function () {
    $rules = BlogContent::category('rules');
    $post = BlogContent::published('label', $rules);

    expect(blogSitemapEntries(get('/sitemap-pages.xml')))->toHaveCount(30 + 3)
        ->and(blogSitemapEntries(get('/sitemap-posts.xml')))->toHaveCount(3)
        ->and(blogSitemapEntries(get('/sitemap-categories.xml')))->toHaveCount(3);

    app(UnpublishBlogPostAction::class)->execute($post->id);

    expect(blogSitemapEntries(get('/sitemap-pages.xml')))->toHaveCount(30)
        ->and(blogSitemapEntries(get('/sitemap-posts.xml')))->toBe([])
        ->and(blogSitemapEntries(get('/sitemap-categories.xml')))->toBe([]);
});
