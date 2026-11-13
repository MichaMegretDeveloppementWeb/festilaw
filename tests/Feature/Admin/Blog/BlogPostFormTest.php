<?php

use App\Enums\Blog\BlogPostStatus;
use App\Livewire\Admin\Blog\PostForm;
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

/*
 | Formulaire d'article du back-office. Le contenu des trois langues n'est pas dans l'etat du composant :
 | le navigateur le detient et le remet en argument a chaque enregistrement (ici, $contents).
 */

uses(RefreshDatabase::class);

beforeEach(fn () => actingAs(User::factory()->create()));

/** Contenu vide dans les trois langues, tel que le navigateur l'envoie pour un article neuf. */
function emptyContents(): array
{
    return ['en' => null, 'fr' => null, 'es' => null];
}

it('opens empty for a new article, with only the categories to load', function () {
    BlogContent::category('rules');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $form = Livewire::test(PostForm::class);

    expect(count(DB::getQueryLog()))->toBe(1);
    DB::disableQueryLog();

    $form->assertOk()
        ->assertSet('postId', null)
        ->assertSet('texts.fr.title', '')
        ->assertSet('slugEdited', ['en' => false, 'fr' => false, 'es' => false])
        ->assertViewHas('initialContents', emptyContents())
        ->assertViewHas('categoryOptions', fn (array $options): bool => array_values($options) === ['Rules fr'])
        ->assertSee('Pas encore enregistré');
});

it('opens an article with its texts, hands its content to the browser once, in 5 queries', function () {
    $rules = BlogContent::category('rules');
    $post = BlogContent::published('label', $rules, CarbonImmutable::parse('2026-11-02 09:00:00'));
    $post->update(['sources' => [['label' => 'Regulation', 'url' => 'https://eur-lex.europa.eu/x']]]);
    $post->relatedPosts()->attach(BlogContent::draft('other')->id, ['position' => 0]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $form = Livewire::test(PostForm::class, ['post' => $post->id]);

    // Article, textes avec contenu, images, articles lies, categories.
    expect(count(DB::getQueryLog()))->toBe(5);
    DB::disableQueryLog();

    $form->assertSet('postId', $post->id)
        ->assertSet('texts.es.title', 'Label es')
        ->assertSet('texts.fr.slug', 'label-fr')
        ->assertSet('categoryId', (string) $rules->id)
        ->assertSet('featuredImageId', $post->featured_image_id)
        ->assertSet('sources', [['label' => 'Regulation', 'url' => 'https://eur-lex.europa.eu/x']])
        // Une adresse deja enregistree ne suit plus le titre.
        ->assertSet('slugEdited', ['en' => true, 'fr' => true, 'es' => true])
        ->assertViewHas('initialContents', BlogContent::contentsOf($post))
        ->assertSee('Publié le 02/11/2026')
        // Un article publie n'affiche plus ce qui « bloque la publication ».
        ->assertDontSee('Rien ne bloque la publication.')
        ->assertSee('Other fr')
        ->assertSee('/fr/blog/label-fr');

    // Aux allers-retours suivants, le contenu n'est plus remis ni relu.
    DB::flushQueryLog();
    DB::enableQueryLog();

    $form->call('addSource')->assertViewHas('initialContents', null)->assertCount('sources', 2);

    expect(count(DB::getQueryLog()))->toBe(5)
        ->and(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'content'))->all())->toBe([]);

    DB::disableQueryLog();
});

it('creates a draft from the texts typed, then reopens it on its own page', function () {
    $rules = BlogContent::category('rules');

    Livewire::test(PostForm::class)
        ->set('texts.fr.title', 'Étiquette : ce qu\'il faut savoir')
        ->set('texts.fr.excerpt', 'Un extrait.')
        ->set('categoryId', (string) $rules->id)
        ->call('save', ['en' => null, 'fr' => BlogContent::document('Bonjour le monde.'), 'es' => null])
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.blog.posts.edit', ['post' => BlogPost::query()->sole()->id]));

    $post = BlogPost::query()->with('translations')->sole();

    expect($post->status)->toBe(BlogPostStatus::Draft)
        ->and($post->blog_category_id)->toBe($rules->id)
        ->and($post->translationFor('fr')->slug)->toBe('etiquette-ce-qu-il-faut-savoir')
        ->and($post->translationFor('fr')->content_html)->toContain('Bonjour le monde.')
        ->and($post->translationFor('en')->title)->toBeNull()
        ->and(session('toast-success'))->toBe('Article créé.');
});

it('saves an article without touching the content the browser hands back', function () {
    $post = BlogContent::published('label');
    $contents = BlogContent::contentsOf($post);

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->set('texts.fr.title', 'Nouveau titre')
        ->call('save', $contents)
        ->assertHasNoErrors()
        ->assertReturned($post->id)
        ->assertNoRedirect()
        ->assertDispatched('blog-post-saved')
        ->assertDispatched('ui-toast', type: 'success')
        // L'adresse d'un article enregistre ne suit pas le nouveau titre.
        ->assertSet('texts.fr.slug', 'label-fr');

    expect(BlogContent::contentsOf($post))->toBe($contents)
        ->and($post->fresh()->translationFor('fr')->title)->toBe('Nouveau titre')
        ->and($post->fresh()->status)->toBe(BlogPostStatus::Published);
});

it('refuses to save when the content of a language was not handed back, rather than erasing it', function () {
    $post = BlogContent::published('label');
    $contents = BlogContent::contentsOf($post);
    unset($contents['es']);

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->set('texts.fr.title', 'Nouveau titre')
        ->call('save', $contents)
        ->assertReturned(null)
        ->assertHasErrors('form')
        ->assertSee('Le contenu de l\'article n\'a pas été transmis.');

    expect($post->fresh()->translationFor('fr')->title)->toBe('Label fr')
        ->and(BlogContent::contentsOf($post)['es'])->not->toBeNull();
});

it('proposes the address from the title, with the suffix that keeps it unique', function () {
    BlogContent::published('label');

    Livewire::test(PostForm::class)
        ->set('texts.fr.title', 'L\'étiquetage GPSR : ce qu\'il faut savoir')
        ->call('suggestSlug', 'fr')->assertReturned('l-etiquetage-gpsr-ce-qu-il-faut-savoir')
        ->set('texts.fr.title', 'Label fr')
        ->call('suggestSlug', 'fr')->assertReturned('label-fr-2')
        ->set('texts.fr.title', '¿?')
        ->call('suggestSlug', 'fr')->assertReturned('')
        ->call('suggestSlug', 'de')->assertReturned('');
});

it('says precisely why an address typed by hand is refused, and saves nothing', function () {
    BlogContent::published('label');
    $post = BlogContent::draft('pending');

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->set('texts.fr.slug', 'label-fr')
        ->set('texts.fr.title', 'Autre titre')
        ->call('save', BlogContent::contentsOf($post))
        ->assertReturned(null)
        ->assertHasErrors('form')
        ->assertSee('est déjà utilisée par un autre article (français)');

    expect($post->fresh()->translationFor('fr')->title)->toBe('Pending fr');
});

it('publishes in one click an article that is ready', function () {
    $post = BlogContent::draft('pending');

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->set('texts.fr.excerpt', 'Extrait retouché.')
        ->call('publish', BlogContent::contentsOf($post))
        ->assertHasNoErrors()
        ->assertReturned($post->id)
        ->assertRedirect(route('admin.blog.posts.edit', ['post' => $post->id]));

    expect($post->fresh()->status)->toBe(BlogPostStatus::Published)
        ->and($post->fresh()->translationFor('fr')->excerpt)->toBe('Extrait retouché.')
        ->and(session('toast-success'))->toBe('Article publié.');
});

it('keeps the article saved as a draft and says what is missing when it is not ready to publish', function () {
    $post = BlogContent::draft('pending');
    $contents = BlogContent::contentsOf($post);
    $contents['es'] = null;

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->set('texts.fr.title', 'Titre retouché')
        ->set('texts.es.title', '')
        ->set('texts.es.slug', '')
        ->call('publish', $contents)
        ->assertReturned($post->id)
        ->assertRedirect(route('admin.blog.posts.edit', ['post' => $post->id]));

    expect($post->fresh()->status)->toBe(BlogPostStatus::Draft)
        // La saisie n'est pas perdue.
        ->and($post->fresh()->translationFor('fr')->title)->toBe('Titre retouché')
        ->and(session('toast-warning'))->toContain('titre (espagnol)')->toContain('contenu (espagnol)')
        ->and(session('toast-success'))->toBeNull();

    // A la reouverture, l'encadre dit ce qui bloque, langue par langue.
    Livewire::test(PostForm::class, ['post' => $post->id])
        ->assertSee('Il manque pour publier')
        ->assertSee('titre (espagnol)')
        ->assertSee('contenu (espagnol)')
        ->assertDontSee('titre (français)');
});

it('publishes a brand-new article only when its three languages are written', function () {
    Livewire::test(PostForm::class)
        ->set('texts.fr.title', 'Un seul titre')
        ->call('publish', ['en' => null, 'fr' => BlogContent::document(), 'es' => null]);

    $post = BlogPost::query()->sole();

    expect($post->status)->toBe(BlogPostStatus::Draft)
        ->and(session('toast-warning'))->toContain('titre (anglais)')->toContain('image principale')->toContain('catégorie');
});

it('schedules the publication at the UTC time typed', function () {
    $post = BlogContent::draft('pending');
    $at = CarbonImmutable::now('UTC')->addDays(3)->setTime(9, 30);

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->set('scheduledFor', $at->format('Y-m-d\TH:i'))
        ->call('schedule', BlogContent::contentsOf($post))
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.blog.posts.edit', ['post' => $post->id]));

    expect($post->fresh()->status)->toBe(BlogPostStatus::Scheduled)
        ->and($post->fresh()->scheduled_for->equalTo($at))->toBeTrue()
        ->and(session('toast-success'))->toContain($at->format('d/m/Y à H:i'));

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->assertSet('scheduledFor', $at->format('Y-m-d\TH:i'))
        ->assertSee('Publication prévue le '.$at->format('d/m/Y à H:i').' (UTC)')
        ->assertSee('Annuler la programmation');
});

it('asks for a date to schedule, and refuses a past one without creating anything', function () {
    Livewire::test(PostForm::class)
        ->call('schedule', emptyContents())
        ->assertHasErrors(['scheduledFor' => 'required'])
        ->set('scheduledFor', 'demain')
        ->call('schedule', emptyContents())
        ->assertHasErrors(['scheduledFor' => 'date_format'])
        ->set('scheduledFor', CarbonImmutable::now('UTC')->subDay()->format('Y-m-d\TH:i'))
        ->call('schedule', emptyContents())
        ->assertReturned(null)
        ->assertHasErrors('form')
        ->assertSee('La date de publication doit être dans le futur.');

    expect(BlogPost::query()->count())->toBe(0);
});

it('puts a published or scheduled article back to draft', function (string $state) {
    $post = $state === 'published' ? BlogContent::published('label') : BlogContent::scheduled('later');

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->call('unpublish')
        ->assertDispatched('ui-toast', type: 'success')
        ->assertSee('Brouillon');

    expect($post->fresh()->status)->toBe(BlogPostStatus::Draft)
        ->and($post->fresh()->scheduled_for)->toBeNull();
})->with(['published', 'scheduled']);

it('keeps the old address of a published article as a redirection when its address changes', function () {
    $post = BlogContent::published('label');

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->set('texts.fr.slug', 'Étiquette produit')
        ->call('save', BlogContent::contentsOf($post))
        ->assertHasNoErrors()
        // L'adresse saisie revient nettoyee dans le champ.
        ->assertSet('texts.fr.slug', 'etiquette-produit');

    expect(BlogPostRetiredSlug::query()->where('blog_post_id', $post->id)->where('locale', 'fr')->value('slug'))->toBe('label-fr');
});

it('deletes the article and goes back to the list', function () {
    $post = BlogContent::draft('pending');

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->call('delete')
        ->assertRedirect(route('admin.blog.posts.index'));

    expect(BlogPost::query()->count())->toBe(0)
        ->and(session('toast-success'))->toBe('Article supprimé.');
});

it('adds, removes and reorders the sources, and validates them at save', function () {
    $post = BlogContent::draft('pending');

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->call('addSource')->call('addSource')->call('addSource')
        ->set('sources.0.label', 'A')->set('sources.0.url', 'https://a.example')
        ->set('sources.1.label', 'B')->set('sources.1.url', 'https://b.example')
        ->set('sources.2.label', '')->set('sources.2.url', 'ftp://c.example')
        ->call('save', BlogContent::contentsOf($post))
        ->assertHasErrors(['sources.2.label' => 'required', 'sources.2.url' => 'regex'])
        ->assertSee('Le lien de la source doit commencer par http:// ou https://.')
        ->call('removeSource', 2)
        ->call('sortSources', 1, 0)
        ->assertSet('sources', [['label' => 'B', 'url' => 'https://b.example'], ['label' => 'A', 'url' => 'https://a.example']])
        ->call('save', BlogContent::contentsOf($post))
        ->assertHasNoErrors();

    expect($post->fresh()->sources)->toBe([['label' => 'B', 'url' => 'https://b.example'], ['label' => 'A', 'url' => 'https://a.example']]);
});

it('limits the length of every text field, naming the language', function () {
    Livewire::test(PostForm::class)
        ->set('texts.es.title', str_repeat('a', 256))
        ->set('texts.en.excerpt', str_repeat('a', 501))
        ->call('save', emptyContents())
        ->assertHasErrors(['texts.es.title' => 'max', 'texts.en.excerpt' => 'max'])
        ->assertSee('Le titre en espagnol ne peut pas dépasser 255 caractères.');

    expect(BlogPost::query()->count())->toBe(0);
});

it('reports an unexpected failure with a neutral message and keeps the details for the log', function () {
    Log::spy();

    // Panne simulee pendant l'ecriture : la table des textes disparait (la transaction du test la restaure).
    Schema::rename('blog_post_translations', 'blog_post_translations_unavailable');

    Livewire::test(PostForm::class)
        ->set('texts.fr.title', 'Titre')
        ->call('save', emptyContents())
        ->assertReturned(null)
        ->assertDispatched('ui-toast', type: 'error')
        ->assertHasNoErrors('form')
        ->assertDontSee('blog_post_translations');

    Schema::rename('blog_post_translations_unavailable', 'blog_post_translations');

    // Rien n'est reste a moitie ecrit.
    expect(BlogPost::query()->count())->toBe(0);

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'Admin save blog post'));
});

it('says so when the article was deleted from another tab', function () {
    $post = BlogContent::draft('pending');
    $form = Livewire::test(PostForm::class, ['post' => $post->id]);

    $post->delete();

    $form->call('addSource')
        ->assertHasErrors('post-load-failed')
        ->assertSee('Cet article n\'existe plus.');
});

it('shows a banner on an article proposed by the AI', function () {
    $post = BlogContent::draft('machine');
    $post->update(['origin' => 'ai']);

    Livewire::test(PostForm::class, ['post' => $post->id])->assertSee('Article généré par IA');
    Livewire::test(PostForm::class)->assertDontSee('Article généré par IA');
});

it('shows the public path in front of the address, and explains the two Google fields', function () {
    Livewire::test(PostForm::class)
        ->assertViewHas('addressPrefixes', ['en' => '/blog/', 'fr' => '/fr/blog/', 'es' => '/es/blog/'])
        ->assertSee('Résultat dans Google')
        ->assertSee('Facultatif : le titre de l\'article sinon.')
        ->assertSee('Facultatif : l\'extrait sinon.')
        ->assertSee('Créée à partir du titre. Vous pouvez la modifier.')
        ->assertDontSee('Proposée automatiquement');
});

it('offers the preview until the article is online, then its public pages, never both', function () {
    $draft = BlogContent::draft('pending', BlogContent::category('rules'));

    Livewire::test(PostForm::class, ['post' => $draft->id])
        ->assertSee('Aperçu en français')
        ->assertDontSee('Voir la page')
        ->assertSee('Publier maintenant')
        ->assertSee('Programmer la publication')
        ->assertDontSee('Dépublier');

    $published = BlogContent::published('label');

    Livewire::test(PostForm::class, ['post' => $published->id])
        ->assertSee('Voir la page en français')
        ->assertSeeHtml('href="'.url('/fr/blog/label-fr').'"')
        ->assertDontSee('Aperçu en')
        ->assertSee('Dépublier')
        ->assertDontSee('Publier maintenant');
});

it('says an article is ready to publish only while it is not published', function () {
    $draft = BlogContent::draft('pending', BlogContent::category('rules'));

    Livewire::test(PostForm::class, ['post' => $draft->id])->assertSee('Prêt à publier');

    $published = BlogContent::published('label', BlogContent::category('news', position: 1));

    Livewire::test(PostForm::class, ['post' => $published->id])
        ->assertDontSee('Prêt à publier')
        ->assertDontSee('à compléter');
});

it('counts what is missing in the header of an incomplete draft', function () {
    $post = BlogContent::draft('pending', BlogContent::category('rules'));
    $post->translations()->where('locale', 'es')->update(['title' => null]);

    Livewire::test(PostForm::class, ['post' => $post->id])
        ->assertSee('1 point à compléter')
        ->assertSee('À compléter avant de publier')
        ->assertSee('titre (espagnol)')
        ->assertDontSee('Prêt à publier');
});

it('keeps a single save for the whole article, and the delete out of the way in the menu', function () {
    $post = BlogContent::draft('pending', BlogContent::category('rules'));

    $html = Livewire::test(PostForm::class, ['post' => $post->id])
        ->assertSee('Réglages de l\'article')
        ->assertSee('Communs aux trois langues')
        ->assertSee('Supprimer l\'article')
        ->html();

    expect(substr_count($html, 'x-on:click="save()"'))->toBe(1)
        ->and($html)->toContain('ui-combobox')
        ->and($html)->not->toContain('Retour aux articles');
});
