<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogContent;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 | Apercu d'un article depuis le back-office : la page publique de l'article, quel que soit son statut,
 | reservee aux comptes connectes et jamais indexee.
 */

uses(RefreshDatabase::class);

it('shows a draft as it will look once published, in the language asked', function (string $locale) {
    $post = BlogContent::draft('pending', BlogContent::category('rules'));
    actingAs(User::factory()->create());

    $response = get("/admin/blog/posts/{$post->id}/preview/{$locale}")->assertOk();

    $response->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<h1 class="article-hero__title">Pending '.$locale.'</h1>', false)
        ->assertSee("<p>Body of pending in {$locale}.</p>", false)
        ->assertSee('Rules '.$locale)
        ->assertSee(__('Preview · this is how the article will look once published.', [], $locale))
        // Jamais indexe, aucune donnee enrichie d'article, aucun equivalent annonce aux moteurs.
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertDontSee('"BlogPosting"', false)
        ->assertDontSee('hreflang', false);

    // Le selecteur de langue reste dans l'apercu.
    foreach (array_diff(BlogContent::LOCALES, [$locale]) as $other) {
        $response->assertSee('href="'.url("/admin/blog/posts/{$post->id}/preview/{$other}").'"', false);
    }

    // Le brouillon reste invisible sur le site public.
    get(localized_route('blog.show', ['slug' => "pending-{$locale}"], $locale))->assertNotFound();
})->with(BlogContent::LOCALES);

it('previews a published article too, with its real publication date', function () {
    $post = BlogContent::published('label', null, CarbonImmutable::parse('2026-11-02 09:00:00'));
    actingAs(User::factory()->create());

    get("/admin/blog/posts/{$post->id}/preview/fr")->assertOk()
        ->assertSee('<time datetime="2026-11-02">', false)
        ->assertSee('Label fr');
});

it('answers not found for an unknown article or a language the site does not have', function () {
    $post = BlogContent::draft('pending');
    actingAs(User::factory()->create());

    get('/admin/blog/posts/999/preview/fr')->assertNotFound();
    get("/admin/blog/posts/{$post->id}/preview/de")->assertNotFound();
});

it('keeps the public article page unchanged', function () {
    BlogContent::published('label');

    get('/fr/blog/label-fr')->assertOk()
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('"BlogPosting"', false)
        ->assertDontSee('article-preview', false);
});
