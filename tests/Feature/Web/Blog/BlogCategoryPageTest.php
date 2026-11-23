<?php

use App\Models\BlogCategoryRetiredSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogContent;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('lists only the published posts of the category, in the page language', function (string $locale) {
    $rules = BlogContent::category('rules');
    $platforms = BlogContent::category('platforms', position: 1);
    BlogContent::published('label', $rules);
    BlogContent::published('etsy', $platforms);
    BlogContent::draft('pending', $rules);

    $url = localized_route('blog.category', ['slug' => "rules-{$locale}"], $locale);
    $response = get($url)->assertOk();

    $response->assertSee('<h1 class="page-hero__title">Rules '.$locale.'</h1>', false)
        ->assertSee('About rules in '.$locale.'.')
        ->assertSee('Label '.$locale)
        ->assertDontSee('Etsy '.$locale)
        ->assertDontSee('Pending '.$locale)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('"CollectionPage"', false);

    foreach (BlogContent::LOCALES as $alternate) {
        $response->assertSee('<link rel="alternate" hreflang="'.$alternate.'" href="'.localized_route('blog.category', ['slug' => "rules-{$alternate}"], $alternate).'">', false);
    }

    expect(substr_count($response->getContent(), 'blog-cats__link is-active'))->toBe(1);
    $response->assertSeeInOrder(['blog-cats__link is-active', 'Rules '.$locale, 'Platforms '.$locale], false);
})->with(BlogContent::LOCALES);

it('answers not found for an unknown category and for a category without any published post', function () {
    $empty = BlogContent::category('empty');
    BlogContent::draft('pending', $empty);
    BlogContent::published('guide');

    get('/blog/category/unknown')->assertNotFound()->assertDontSee('hreflang', false);
    get('/blog/category/empty-en')->assertNotFound();
    get('/fr/blog/categorie/empty-en')->assertNotFound();
});

it('redirects an old category address to the current one, in the same language', function () {
    $rules = BlogContent::category('rules');
    BlogContent::published('label', $rules);
    BlogCategoryRetiredSlug::factory()->create(['blog_category_id' => $rules->id, 'locale' => 'fr', 'slug' => 'ancien-nom']);

    get('/fr/blog/categorie/ancien-nom')->assertRedirect(url('/fr/blog/categorie/rules-fr'))->assertStatus(301);
    get('/blog/category/ancien-nom')->assertNotFound();
});

it('paginates a category twelve by twelve', function () {
    $rules = BlogContent::category('rules');
    BlogContent::seedPublished(13, $rules);

    $first = get('/blog/category/rules-en')->assertOk();
    expect(substr_count($first->getContent(), 'class="post-card"'))->toBe(12);

    $second = get('/blog/category/rules-en?page=2')->assertOk();
    expect(substr_count($second->getContent(), 'class="post-card"'))->toBe(1);
    $second->assertSee('<link rel="canonical" href="'.url('/blog/category/rules-en').'?page=2">', false)
        ->assertSee('<link rel="alternate" hreflang="es" href="'.url('/es/blog/categoria/rules-es').'?page=2">', false)
        // Audit du 23/11 : la page 2 porte son numero, Google ne la compte plus comme un doublon de la page 1.
        ->assertSee('<title>Rules en · page 2 · Blog · Festilaw</title>', false);
    $first->assertSee('<title>Rules en · Blog · Festilaw</title>', false);
    get('/es/blog/categoria/rules-es?page=2')->assertSee('<title>Rules es · página 2 · Blog · Festilaw</title>', false);

    get('/blog/category/rules-en?page=3')->assertNotFound();
});
