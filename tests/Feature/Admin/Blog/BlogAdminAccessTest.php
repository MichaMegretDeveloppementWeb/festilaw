<?php

use App\Models\User;
use App\Services\Admin\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogContent;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 | Back-office du blog : acces reserve aux comptes connectes, et menu decrit une seule fois pour les
 | deux rendus de la mise en page (menu mobile, barre laterale).
 */

uses(RefreshDatabase::class);

it('sends a visitor who is not signed in to the login page', function (string $path) {
    $post = BlogContent::draft('pending');

    get(str_replace('{post}', (string) $post->id, $path))->assertRedirect(route('admin.login'));
})->with([
    'post list' => ['/admin/blog/posts'],
    'new post' => ['/admin/blog/posts/create'],
    'post form' => ['/admin/blog/posts/{post}/edit'],
    'preview' => ['/admin/blog/posts/{post}/preview/fr'],
    'categories' => ['/admin/blog/categories'],
    'new category' => ['/admin/blog/categories/create'],
]);

it('serves every blog screen to a signed-in admin', function () {
    $post = BlogContent::published('guide', BlogContent::category('rules'));
    actingAs(User::factory()->create());

    get('/admin/blog/posts')->assertOk()->assertSee('Guide fr');
    get('/admin/blog/posts/create')->assertOk()->assertSee('Nouvel article');
    get("/admin/blog/posts/{$post->id}/edit")->assertOk()->assertSee('Modifier l\'article');
    get('/admin/blog/categories')->assertOk()->assertSee('Rules fr');
    get('/admin/blog/categories/create')->assertOk()->assertSee('Nouvelle catégorie');
    get("/admin/blog/categories/{$post->blog_category_id}/edit")->assertOk()->assertSee('Modifier la catégorie');
});

it('answers not found for an article or a category that does not exist', function () {
    actingAs(User::factory()->create());

    get('/admin/blog/posts/999/edit')->assertNotFound();
    get('/admin/blog/categories/999/edit')->assertNotFound();
    get('/admin/blog/posts/abc/edit')->assertNotFound();
});

it('shows the blog group in both menus and marks the current screen', function () {
    actingAs(User::factory()->create());

    $content = get('/admin/blog/categories')->assertOk()->getContent();

    // Les memes liens dans le menu mobile et dans la barre laterale.
    expect(substr_count($content, 'href="'.route('admin.blog.posts.index').'"'))->toBe(2)
        ->and(substr_count($content, 'href="'.route('admin.blog.categories.index').'"'))->toBe(2)
        ->and(substr_count($content, 'href="'.route('admin.submissions.index').'"'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($content, 'aria-current="page"'))->toBe(2);
});

it('describes the menu once, with a single active item per screen', function (string $route, bool $viewingContact, string $active) {
    $items = collect(app(AdminNavigation::class)->groups($route, $viewingContact))->flatMap->items;

    expect($items->pluck('label')->all())->toBe(['Dossiers', 'Prises de contact', 'Quiz', 'Tarifs', 'Articles', 'Catégories', 'Génération IA'])
        ->and($items->where('active', true)->pluck('label')->all())->toBe([$active]);
})->with([
    'dossiers' => ['admin.submissions.index', false, 'Dossiers'],
    'a contact opened through the dossier route' => ['admin.submissions.show', true, 'Prises de contact'],
    'quiz' => ['admin.quiz.index', false, 'Quiz'],
    'pricing' => ['admin.settings', false, 'Tarifs'],
    'post form' => ['admin.blog.posts.edit', false, 'Articles'],
    'category form' => ['admin.blog.categories.create', false, 'Catégories'],
    'generation settings' => ['admin.blog.generation.settings', false, 'Génération IA'],
    'generation detail' => ['admin.blog.generation.show', false, 'Génération IA'],
]);

it('never shows a doubly escaped apostrophe on a blog screen', function () {
    $post = BlogContent::published('guide', BlogContent::category('rules'));
    actingAs(User::factory()->create());

    // Une propriete de composant recue par {{ }} puis affichee par {{ }} serait echappee deux fois.
    foreach (['/admin/blog/posts', '/admin/blog/posts/create', "/admin/blog/posts/{$post->id}/edit", '/admin/blog/categories', '/admin/blog/categories/create', '/admin/blog/generation', '/admin/blog/generation/settings'] as $path) {
        expect(get($path)->assertOk()->getContent())->not->toContain('&amp;#039;');
    }
});

it('names the screen in the browser tab and in the top bar', function () {
    actingAs(User::factory()->create());

    get('/admin/blog/categories')->assertOk()->assertSee('<title>Catégories · '.config('app.name').'</title>', false);
    get('/admin/blog/posts')->assertOk()->assertSee('<title>Articles · '.config('app.name').'</title>', false);
    get('/admin/submissions')->assertOk()->assertSee('<title>Dossiers · '.config('app.name').'</title>', false);
});

it('draws the back-office with the kit, its files placed before the application stylesheet', function () {
    actingAs(User::factory()->create());

    $content = get('/admin/blog/posts')->assertOk()->getContent();

    $kit = strpos($content, 'vendor/falcon/ui/ui.css');
    $application = strpos($content, 'build/assets/admin-');

    expect($kit)->not->toBeFalse()
        ->and($application)->not->toBeFalse()
        ->and($kit)->toBeLessThan($application)
        ->and($content)->toContain('ui-toast')
        ->and($content)->toContain('ui-sidebar-frame');
});

it('keeps the kit installed the way its own check asks for', function () {
    // Copies publiees a jour, ordre des couches, jetons, conteneur des notifications : un seul controle,
    // celui du kit. Il echoue si public/vendor/falcon n'a pas ete republie apres une mise a jour.
    $this->artisan('ui-kit:check')->assertSuccessful();
});
