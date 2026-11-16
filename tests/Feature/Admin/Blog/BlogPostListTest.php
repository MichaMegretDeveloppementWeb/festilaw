<?php

use App\Enums\Blog\BlogPostListSort;
use App\Enums\Blog\BlogPostOrigin;
use App\Livewire\Admin\Blog\PostList;
use App\Models\BlogPost;
use App\Models\BlogPostRetiredSlug;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Support\BlogContent;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => actingAs(User::factory()->create()));

it('lists every article with its status, category, written languages and origin', function () {
    $rules = BlogContent::category('rules');
    BlogContent::published('label', $rules);
    $draft = BlogContent::draft('pending', $rules);
    $draft->translations()->where('locale', 'es')->update(['title' => null, 'word_count' => 0]);
    BlogPost::factory()->create(['origin' => BlogPostOrigin::Ai]);

    Livewire::test(PostList::class)
        ->assertOk()
        ->assertSee('Label fr')
        ->assertSee('Pending fr')
        ->assertSee('Sans titre')
        ->assertSee('Rules fr')
        ->assertSee('Publié')
        ->assertSee('Brouillon')
        ->assertSee('IA')
        ->assertViewHas('posts', function ($posts) use ($draft) {
            $row = collect($posts->items())->firstWhere('id', $draft->id);

            // L'espagnol n'est plus redige : il manque le titre et le contenu.
            return $row->writtenLocales === ['en', 'fr'] && $row->publicUrl === null && $row->thumbnailUrl !== null;
        });
});

it('gives the public address of a published article only', function () {
    $published = BlogContent::published('label');
    BlogContent::scheduled('later');

    Livewire::test(PostList::class)->assertViewHas('posts', function ($posts) use ($published) {
        $rows = collect($posts->items())->keyBy('id');

        return $rows[$published->id]->publicUrl === url('/fr/blog/label-fr')
            && $rows->where('publicUrl', null)->count() === 1;
    });
});

it('searches the title in any of the three languages', function () {
    BlogContent::published('label');
    BlogContent::published('candles');
    BlogPost::query()->where('status', 'published')->first()->translations()->where('locale', 'es')->update(['title' => 'Etiqueta única']);

    Livewire::test(PostList::class)
        ->set('search', 'candles')->assertSee('Candles fr')->assertDontSee('Label fr')
        ->set('search', 'única')->assertSee('Label fr')->assertDontSee('Candles fr')
        ->set('search', '100%')->assertSee('Aucun article ne correspond.');
});

it('filters by status, category and origin', function () {
    $rules = BlogContent::category('rules');
    $news = BlogContent::category('news', position: 1);
    BlogContent::published('label', $rules);
    BlogContent::draft('pending', $news);
    BlogContent::draft('machine', $news)->update(['origin' => BlogPostOrigin::Ai]);

    Livewire::test(PostList::class)
        ->set('status', 'published')->assertSee('Label fr')->assertDontSee('Pending fr')
        ->set('status', '')->set('category', (string) $news->id)->assertSee('Pending fr')->assertSee('Machine fr')->assertDontSee('Label fr')
        ->set('origin', 'ai')->assertSee('Machine fr')->assertDontSee('Pending fr')
        // Une valeur inconnue dans l'adresse ne casse pas la liste : le filtre est ignore.
        ->set('category', '')->set('origin', 'unknown')->assertSee('Label fr')->assertSee('Pending fr');
});

it('clears the two filters of the panel at once, and keeps the search and the status chosen in the strip', function () {
    $news = BlogContent::category('news');
    BlogContent::published('label', BlogContent::category('rules', position: 1));
    BlogContent::draft('pending', $news);

    Livewire::test(PostList::class)
        // Sans filtre actif, le panneau ne propose rien a reinitialiser ; le statut n'est pas un filtre du panneau.
        ->assertDontSeeHtml('data-clear-filters')
        ->set('status', 'draft')
        ->assertDontSeeHtml('data-clear-filters')
        ->set('search', 'fr')->set('category', (string) $news->id)->set('origin', 'manual')
        ->assertSeeHtml('data-clear-filters')
        ->call('clearFilters')
        ->assertSet('category', '')->assertSet('origin', '')
        ->assertSet('status', 'draft')->assertSet('search', 'fr')
        ->assertSee('Pending fr')->assertDontSee('Label fr')
        ->assertDontSeeHtml('data-clear-filters');
});

it('counts the articles of each status in a one-line segmented filter, which filters the list by status', function () {
    $rules = BlogContent::category('rules');
    $news = BlogContent::category('news', position: 1);
    BlogContent::published('label', $rules);
    BlogContent::published('guide', $news);
    BlogContent::scheduled('soon');
    BlogContent::draft('pending', $news);

    $list = Livewire::test(PostList::class)
        ->assertViewHas('statusCounts', ['draft' => 1, 'scheduled' => 1, 'published' => 2])
        ->assertViewHas('totalCount', 4)
        // Le filtre par statut tient sur une ligne (selecteur segmente du kit), avec le nombre de chaque statut,
        // et plus aucune bande de chiffres ne repousse la liste.
        ->assertDontSee('Tous les statuts')
        ->assertSeeHtml('data-status-filter')
        ->assertSeeHtml('ui-segmented')
        ->assertSeeHtml('wire:model.live="status"')
        ->assertSee('Tous (4)')->assertSee('Publiés (2)')->assertSee('Programmés (1)')->assertSee('Brouillons (1)')
        ->assertDontSeeHtml('aria-pressed');

    // Choisir un statut filtre la liste.
    $list->set('status', 'published')
        ->assertSee('Label fr')->assertSee('Guide fr')->assertDontSee('Pending fr')
        // Les nombres ne dependent pas du statut choisi, mais suivent les autres filtres.
        ->assertViewHas('statusCounts', ['draft' => 1, 'scheduled' => 1, 'published' => 2])
        ->set('category', (string) $news->id)
        ->assertViewHas('statusCounts', ['draft' => 1, 'scheduled' => 0, 'published' => 1])
        ->assertViewHas('totalCount', 2)
        ->assertSee('Tous (2)')->assertSee('Programmés (0)');
});

it('shows fifteen articles per page, newest first, with the count of articles under the list', function () {
    BlogContent::seedPublished(16);

    Livewire::test(PostList::class)
        ->assertSee('Bulk 15 fr')->assertSee('Bulk 1 fr')->assertDontSee('Bulk 0 fr')
        ->assertViewHas('perPageShown', 15)
        ->assertSeeInOrder(['Affichage de', '1', '15', '16', 'résultats'])
        ->call('nextPage')
        ->assertSee('Bulk 0 fr')->assertDontSee('Bulk 15 fr');
});

it('keeps the foot of the list on a single page, to choose how many articles a page shows', function () {
    BlogContent::seedPublished(16);

    Livewire::test(PostList::class)
        ->set('perPage', 25)
        ->assertSee('Bulk 15 fr')->assertSee('Bulk 0 fr')
        // Une seule page : le pied reste la, avec le nombre d'articles et le choix par page.
        ->assertViewHas('posts', fn ($posts): bool => $posts->lastPage() === 1 && $posts->total() === 16)
        ->assertSee('Affichage de')
        ->assertSee('25 par page')->assertSee('100 par page')
        // Un nombre qui n'est pas propose (adresse modifiee a la main) retombe sur le defaut.
        ->set('perPage', 5000)
        ->assertViewHas('perPageShown', 15)
        ->assertDontSee('Bulk 0 fr');
});

it('goes back to the first page when the number of articles per page changes', function () {
    BlogContent::seedPublished(16);

    Livewire::test(PostList::class)
        ->call('nextPage')
        ->assertSee('Bulk 0 fr')
        ->set('perPage', 25)
        ->assertSee('Bulk 15 fr')->assertSee('Bulk 0 fr');
});

it('sorts by the column clicked: ascending, then descending, then newest first again', function () {
    BlogContent::published('banana');
    BlogContent::published('apple');
    BlogContent::published('cherry');

    $titles = fn ($posts): array => collect($posts->items())->pluck('title')->all();

    Livewire::test(PostList::class)
        ->assertViewHas('posts', fn ($posts): bool => $titles($posts) === ['Cherry fr', 'Apple fr', 'Banana fr'])
        ->call('sortBy', 'title')
        ->assertSet('sort', 'title')->assertSet('direction', 'asc')
        ->assertViewHas('posts', fn ($posts): bool => $titles($posts) === ['Apple fr', 'Banana fr', 'Cherry fr'])
        ->call('sortBy', 'title')
        ->assertSet('direction', 'desc')
        ->assertViewHas('posts', fn ($posts): bool => $titles($posts) === ['Cherry fr', 'Banana fr', 'Apple fr'])
        ->call('sortBy', 'title')
        ->assertSet('sort', 'recent')->assertSet('direction', 'desc')
        ->assertViewHas('posts', fn ($posts): bool => $titles($posts) === ['Cherry fr', 'Apple fr', 'Banana fr'])
        // Une colonne inconnue ne change rien.
        ->call('sortBy', 'password')
        ->assertSet('sort', 'recent');
});

it('sorts by status, category and date', function () {
    $rules = BlogContent::category('rules');
    $news = BlogContent::category('news', position: 1);
    $old = BlogContent::published('old', $rules, CarbonImmutable::parse('2026-01-05 09:00:00'));
    $recent = BlogContent::published('recent', $news, CarbonImmutable::parse('2026-06-05 09:00:00'));
    $draft = BlogContent::draft('pending', $rules);

    $ids = fn ($posts): array => collect($posts->items())->pluck('id')->all();

    Livewire::test(PostList::class)
        // Brouillon avant publie ; a statut egal, le plus recent d'abord.
        ->call('sortBy', 'status')
        ->assertViewHas('posts', fn ($posts): bool => $ids($posts) === [$draft->id, $recent->id, $old->id])
        // « News fr » avant « Rules fr ».
        ->call('sortBy', 'category')
        ->assertViewHas('posts', fn ($posts): bool => $ids($posts) === [$recent->id, $draft->id, $old->id])
        ->call('sortBy', 'date')->call('sortBy', 'date')
        ->assertSet('direction', 'desc')
        ->assertViewHas('posts', fn ($posts): bool => array_slice($ids($posts), 0, 2) === [$recent->id, $old->id] || array_slice($ids($posts), -2) === [$recent->id, $old->id]);
});

it('ignores a sort it does not know in the address, and counts the filters in use', function () {
    BlogContent::published('label', BlogContent::category('rules'));

    Livewire::withQueryParams(['sort' => 'drop table', 'direction' => 'sideways', 'status' => 'published', 'origin' => 'manual'])
        ->test(PostList::class)
        ->assertOk()
        ->assertSee('Label fr')
        ->assertViewHas('sorted', BlogPostListSort::Recent)
        ->assertViewHas('sortDirection', 'desc')
        // Le statut se lit dans la bande de chiffres : seule l'origine compte pour le panneau des filtres.
        ->assertViewHas('activeFilters', 1);
});

it('opens an article from its whole row, with the other actions in one menu and no edit button', function () {
    $post = BlogContent::published('label');

    Livewire::test(PostList::class)
        ->assertSeeHtml("window.location.href = '".route('admin.blog.posts.edit', ['post' => $post->id])."'")
        ->assertSeeHtml('data-ui-row-menu="posts"')
        ->assertDontSeeHtml('title="Modifier"')
        ->assertSee('Supprimer cet article ?');
});

it('deletes an article, and keeps the addresses of a published one answering that the page is gone', function () {
    $published = BlogContent::published('label');
    $draft = BlogContent::draft('pending');

    Livewire::test(PostList::class)
        ->call('delete', $published->id)
        ->call('delete', $draft->id)
        ->assertDispatched('ui-toast', type: 'success')
        ->assertDontSee('Label fr')
        ->assertDontSee('Pending fr');

    expect(BlogPost::query()->count())->toBe(0)
        ->and(BlogPostRetiredSlug::query()->whereNull('blog_post_id')->where('slug', 'label-fr')->exists())->toBeTrue()
        ->and(BlogPostRetiredSlug::query()->where('slug', 'pending-fr')->exists())->toBeFalse();
});

it('loads the list in 6 queries whatever the number of articles', function () {
    BlogContent::seedPublished(60, BlogContent::category('rules'), BlogContent::category('news', position: 1));

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(PostList::class)->assertOk();

    expect(count(DB::getQueryLog()))->toBe(6);

    DB::disableQueryLog();
});

it('shows a message in place of the list when it cannot be loaded', function () {
    Log::spy();
    Schema::rename('blog_posts', 'blog_posts_unavailable');

    Livewire::test(PostList::class)
        ->assertOk()
        ->assertHasErrors('posts-load-failed')
        ->assertSee('Le chargement des articles a échoué.');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'admin blog post list'));

    Schema::rename('blog_posts_unavailable', 'blog_posts');
});
