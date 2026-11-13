<?php

use App\Actions\Blog\Post\UnpublishBlogPostAction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BlogContent;

use function Pest\Laravel\get;

/*
 | Le blog dans le reste du site : lien "Blog" de l'en-tete, du menu mobile et du pied de page, et bloc
 | "derniers articles" de l'accueil. Les deux n'apparaissent qu'a partir du premier article publie, et
 | une panne de la lecture du blog ne doit jamais empecher une page du site de s'afficher.
 */

uses(RefreshDatabase::class);

it('hides the blog link and the home block while nothing is published', function () {
    BlogContent::draft('pending');
    BlogContent::scheduled('later');

    get('/')->assertOk()
        ->assertDontSee('href="'.url('/blog').'"', false)
        ->assertDontSee('class="latest-posts"', false);

    get('/fr/pricing')->assertOk()->assertDontSee('href="'.url('/fr/blog').'"', false);
});

it('links to the blog from the header, the mobile menu and the footer once a post is published', function (string $locale) {
    BlogContent::published('guide');

    $content = get(localized_route('pricing', [], $locale))->assertOk()->getContent();

    expect(substr_count($content, 'href="'.localized_route('blog.index', [], $locale).'"'))->toBe(3);
})->with(BlogContent::LOCALES);

it('removes the blog link and the home block when the last post is unpublished', function () {
    $post = BlogContent::published('guide');

    get('/')->assertOk()
        ->assertSee('href="'.url('/blog').'"', false)
        ->assertSee('class="latest-posts"', false);

    app(UnpublishBlogPostAction::class)->execute($post->id);

    get('/')->assertOk()
        ->assertDontSee('href="'.url('/blog').'"', false)
        ->assertDontSee('class="latest-posts"', false);
});

it('shows the three latest posts on the home page, in the language of the page', function (string $locale) {
    $category = BlogContent::category('rules');
    BlogContent::published('first', $category, CarbonImmutable::parse('2026-10-01 09:00:00'));
    BlogContent::published('second', $category, CarbonImmutable::parse('2026-10-02 09:00:00'));
    BlogContent::published('third', $category, CarbonImmutable::parse('2026-10-03 09:00:00'));
    BlogContent::published('fourth', $category, CarbonImmutable::parse('2026-10-04 09:00:00'));
    BlogContent::draft('hidden', $category);

    $response = get(localized_route('home', [], $locale))->assertOk();

    $response->assertSeeInOrder(['class="latest-posts"', 'Fourth '.$locale, 'Third '.$locale, 'Second '.$locale], false)
        ->assertDontSee('First '.$locale)
        ->assertDontSee('Hidden '.$locale)
        ->assertSee('href="'.localized_route('blog.show', ['slug' => "fourth-{$locale}"], $locale).'"', false)
        ->assertSee('loading="lazy"', false);

    expect(substr_count($response->getContent(), 'class="post-card"'))->toBe(3);

    // Un seul titre principal sur l'accueil : les cartes du bloc sont des titres de niveau 3.
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
})->with(BlogContent::LOCALES);

it('still serves the site, without the blog link nor the home block, when the blog cannot be read', function () {
    BlogContent::published('guide');
    Log::spy();

    // Panne simulee : la table des articles disparait (la transaction du test la restaure).
    Schema::rename('blog_posts', 'blog_posts_unavailable');

    get('/')->assertOk()
        ->assertSee('Your GPSR', false)
        ->assertDontSee('href="'.url('/blog').'"', false)
        ->assertDontSee('class="latest-posts"', false);

    get('/fr/pricing')->assertOk()->assertDontSee('href="'.url('/fr/blog').'"', false);

    get('/sitemap.xml')->assertOk()
        ->assertSee('<loc>'.url('/pricing').'</loc>', false)
        ->assertDontSee('/blog', false);

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'Blog navigation check failed'));
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'Home blog block failed'));
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'Blog sitemap entries failed'));

    Schema::rename('blog_posts_unavailable', 'blog_posts');
});
