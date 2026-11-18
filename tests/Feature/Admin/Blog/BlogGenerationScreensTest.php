<?php

use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Livewire\Admin\Blog\GenerationActivity;
use App\Livewire\Admin\Blog\GenerationDashboard;
use App\Livewire\Admin\Blog\GenerationDetail;
use App\Livewire\Admin\Blog\GenerationHistory;
use App\Livewire\Admin\Blog\GenerationLauncher;
use App\Livewire\Admin\Blog\GenerationSettings;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Models\BlogPost;
use App\Models\Setting;
use App\Models\User;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationWorkerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Support\BlogAi;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 | Ecrans de la generation par IA au back-office : vue d'ensemble (depense, automatique, brouillons a relire,
 | tuile « En cours », dernieres generations), fenetre de lancement, historique, page d'une generation,
 | reglages. Les etapes sont les etapes factices de la configuration de test : aucun modele n'est appele.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Http::preventStrayRequests();
});

it('sends a visitor who is not signed in to the login page', function (string $path) {
    $generation = BlogGeneration::factory()->create();

    get(str_replace('{generation}', (string) $generation->id, $path))->assertRedirect(route('admin.login'));
})->with([
    'overview' => ['/admin/blog/generation'],
    'history' => ['/admin/blog/generation/history'],
    'settings' => ['/admin/blog/generation/settings'],
    'generation' => ['/admin/blog/generation/{generation}'],
]);

it('serves every generation page to a signed-in admin, under one menu entry and without tabs', function () {
    $generation = BlogGeneration::factory()->completed()->create(['topic_hint' => 'Labels on Etsy']);
    actingAs(User::factory()->create());

    // La page d'arrivee mene a l'historique par son lien et aux reglages par sa roue crantee.
    get('/admin/blog/generation')->assertOk()
        ->assertSee('Générer un article')
        ->assertSee('href="'.route('admin.blog.generation.history').'"', false)
        ->assertSee('href="'.route('admin.blog.generation.settings').'"', false)
        ->assertSee('data-open-settings', false)
        ->assertSee('Réglages de la génération IA')
        ->assertDontSee('Vue d\'ensemble')
        ->assertDontSee('data-back-to-generation', false);

    // L'historique et les reglages sont deux pages a part, avec un lien de retour. Aucun onglet nulle part.
    get('/admin/blog/generation/history')->assertOk()
        ->assertSee('Historique des générations')
        ->assertSee('data-back-to-generation', false)
        ->assertSee('Générer un article')
        ->assertDontSee('aria-current="page">Historique', false);

    get('/admin/blog/generation/settings')->assertOk()
        ->assertSee('Réglages de la génération IA')
        ->assertSee('data-back-to-generation', false)
        // Pas de bouton de lancement sur la page des reglages.
        ->assertDontSee('data-launch-generation', false);

    foreach (['/admin/blog/generation', '/admin/blog/generation/history', '/admin/blog/generation/settings'] as $path) {
        get($path)->assertSee('Génération IA')->assertDontSee('Pages de cette rubrique');
    }

    get('/admin/blog/generation')->assertSee('<title>Génération IA · '.config('app.name').'</title>', false)->assertSee('Labels on Etsy');
    get('/admin/blog/generation/history')->assertSee('Labels on Etsy');
    get("/admin/blog/generation/{$generation->id}")->assertOk()->assertSee("Génération n° {$generation->id}.")->assertSee('Labels on Etsy');
    get('/admin/blog/generation/not-a-number')->assertNotFound();
});

// --- Vue d'ensemble -------------------------------------------------------------------------------

it('shows the spend of the month against its cap, the automatic rhythm and the drafts waiting for a review', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 400, 'enabled' => true, 'articles_per_run' => 2]);
    BlogGeneration::factory()->completed(costMicrousd: 1_000_000)->create();
    BlogGeneration::factory()->completed(costMicrousd: 9_000_000)->create(['created_at' => now()->subMonthNoOverflow()]);
    BlogPost::factory()->count(2)->create(['origin' => BlogPostOrigin::Ai]);
    BlogPost::factory()->create();

    Livewire::test(GenerationDashboard::class)
        ->assertSee('1,0000 $')
        ->assertSee('sur 4,0000 $ de plafond')
        ->assertSee('Activée')
        ->assertSee('2 articles chaque semaine')
        ->assertSee('brouillons générés attendent votre relecture')
        ->assertSeeHtml(e(route('admin.blog.posts.index', ['origin' => 'ai', 'status' => 'draft'])))
        // Ni formulaire de lancement ni journal pagine sur cette page : chacun a sa place.
        ->assertDontSee('Lancer une génération')
        ->assertDontSeeHtml('ui-pagination');
});

it('says when a worker is listening, and warns when none is', function () {
    // Dit avec les mots de la personne qui ecrit des articles : ni « traitement », ni « a l'ecoute ».
    Livewire::test(GenerationDashboard::class)
        ->assertSee('Les générations ne démarrent pas pour le moment')
        ->assertSee('Un article demandé maintenant restera en attente.')
        ->assertSee('Service de génération à l\'arrêt')
        ->assertDontSee('traitement')
        ->assertDontSee('Traitement');

    app(BlogGenerationWorkerRegistry::class)->register('worker-a');

    Livewire::test(GenerationDashboard::class)
        ->assertSee('Service de génération en marche')
        ->assertDontSee('Les générations ne démarrent pas pour le moment');

    $this->travel(5)->minutes();

    Livewire::test(GenerationDashboard::class)->assertSee('Service de génération à l\'arrêt');
});

it('tells whether the provider key is there in the service state, without ever showing it', function () {
    config(['ai.providers.openrouter.key' => 'sk-or-secret-value-123']);
    actingAs(User::factory()->create());

    Livewire::test(GenerationDashboard::class)
        ->assertSee('État du service')
        ->assertSee('Clé du fournisseur d\'IA en place')
        ->assertDontSee('sk-or-secret-value-123');

    foreach (['/admin/blog/generation', '/admin/blog/generation/history', '/admin/blog/generation/settings'] as $path) {
        expect(get($path)->getContent())->not->toContain('sk-or-secret-value-123');
    }

    config(['ai.providers.openrouter.key' => null]);

    Livewire::test(GenerationDashboard::class)->assertSee('Clé du fournisseur d\'IA absente');
});

it('says in plain words that the generation is unavailable when the key is missing outside of the trial mode', function () {
    config(['ai.providers.openrouter.key' => null, 'blog.ai.pipeline' => 'editorial']);

    Livewire::test(GenerationDashboard::class)
        ->assertSee('La génération n\'est pas disponible')
        ->assertSee('Contactez l\'assistance technique.');
});

it('lists the five latest generations, newest first, with their outcome and no technical message', function () {
    BlogGeneration::factory()->count(3)->completed()->create();
    $old = BlogGeneration::factory()->completed(costMicrousd: 262_500)->create(['topic_title' => 'CE marking for toys']);
    BlogGeneration::factory()->scheduled()->abandoned(BlogGenerationOutcomeReason::SpendCapReached)->create();
    $failed = BlogGeneration::factory()->failed()->create(['topic_hint' => 'Labels on Etsy']);
    BlogGenerationStep::factory()->for($failed, 'generation')->named('writing', 3)->failed()->create();

    $dashboard = Livewire::test(GenerationDashboard::class)
        ->assertSeeInOrder(['Labels on Etsy', 'Échec à l\'étape « Rédaction »', 'Plafond de dépense du mois atteint', 'CE marking for toys'])
        ->assertDontSee('Simulated provider failure.')
        ->assertSee('Automatique')
        ->assertSee('En échec')
        ->assertSee('0,2625 $')
        ->assertSee('Tout l\'historique')
        ->assertSeeHtml('wire:click="retry('.$failed->id.')"')
        ->assertDontSeeHtml('wire:click="retry('.$old->id.')"');

    // Six generations existent : la plus ancienne n'est que dans l'historique.
    expect(substr_count($dashboard->html(), 'data-generation="'))->toBe(5);
});

it('says so when there is no generation yet', function () {
    Livewire::test(GenerationDashboard::class)->assertSee('Aucune génération pour le moment.');
});

it('restarts a failed generation from the overview', function () {
    $failed = BlogGeneration::factory()->failed()->create();
    $running = BlogGeneration::factory()->running()->create();
    Log::spy();

    Livewire::test(GenerationDashboard::class)
        ->call('retry', $failed->id)
        ->assertDispatched('ui-toast', type: 'success', title: 'Génération relancée.')
        ->assertDispatched('blog-generation-changed')
        ->call('retry', $running->id)
        ->assertDispatched('ui-toast', type: 'error', title: 'Seule une génération en échec peut être relancée.');

    expect($failed->refresh()->status)->toBe(BlogGenerationStatus::Queued);
});

it('shows a load failure instead of the overview when the data cannot be read', function () {
    Log::spy();

    // Panne simulee : la table des generations disparait (la transaction du test la restaure).
    Schema::rename('blog_generations', 'blog_generations_unavailable');

    Livewire::test(GenerationDashboard::class)
        ->assertSee('Le chargement de la génération par IA a échoué.')
        ->assertDontSee('Dernières générations');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'admin blog generation overview'));

    Schema::rename('blog_generations_unavailable', 'blog_generations');
});

it('loads the overview in 6 queries, plus 1 for the activity tile, whatever the number of generations', function () {
    BlogGeneration::factory()->count(40)->completed()->create()->each(function (BlogGeneration $generation): void {
        BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed()->create();
        BlogGenerationStep::factory()->for($generation, 'generation')->named('writing', 2)->completed()->create();
    });

    DB::enableQueryLog();
    Livewire::test(GenerationDashboard::class);

    // Reglages, depense du mois, worker, brouillons a relire, dernieres generations, etape en echec de celles-ci ;
    // plus la tuile « En cours » (une requete : aucune generation active). Le bouton de lancement n'en fait aucune.
    expect(count(DB::getQueryLog()))->toBe(7);
});

// --- Tuile « En cours » ---------------------------------------------------------------------------

it('keeps the activity tile in place, and checks rarely, while no generation is active', function () {
    BlogGeneration::factory()->completed()->create();

    DB::enableQueryLog();
    $tile = Livewire::test(GenerationActivity::class);

    expect(count(DB::getQueryLog()))->toBe(1);
    $tile->assertSeeHtml('data-generation-activity="idle"')
        ->assertSeeHtml('wire:poll.15s')
        ->assertDontSeeHtml('wire:poll.2s')
        ->assertSee('En cours')
        ->assertSee('Aucune génération en attente ni en cours')
        // Le lien « Suivre » garde sa place, masque : la tuile ne change pas de taille.
        ->assertSeeHtml('invisible');
});

it('says at once that a requested generation is starting', function () {
    $generation = BlogGeneration::factory()->create(['topic_hint' => 'Labels on Etsy']);

    Livewire::test(GenerationActivity::class)
        ->assertSee('En attente')
        ->assertSee('Démarrage dans quelques secondes')
        ->assertSeeHtml('wire:poll.2s')
        ->assertSeeHtml('data-generation-activity="queued"')
        ->assertSeeHtml(route('admin.blog.generation.show', ['generation' => $generation->id]))
        ->assertDontSeeHtml('invisible');
});

it('follows the running generation in the tile, in 2 queries', function () {
    $generation = BlogGeneration::factory()->running()->create(['cost_microusd' => 12_500]);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed(12_500)->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('research', 2)->running()->create();

    DB::enableQueryLog();
    $tile = Livewire::test(GenerationActivity::class);

    expect(count(DB::getQueryLog()))->toBe(2);
    $tile->assertSee('Étape 2 sur 9')
        ->assertSee('Dossier de faits')
        ->assertSee('Suivre la génération')
        ->assertSeeHtml('data-generation-activity="running"');
});

it('tells the page when the generation it shows changes state', function () {
    $generation = BlogGeneration::factory()->running()->create();

    $tile = Livewire::test(GenerationActivity::class)->assertNotDispatched('blog-generation-progressed');

    $tile->call('refreshActivity')->assertNotDispatched('blog-generation-progressed');

    $generation->update(['status' => BlogGenerationStatus::Completed, 'locked_until' => null, 'finished_at' => now()]);

    $tile->call('refreshActivity')
        ->assertDispatched('blog-generation-progressed')
        ->assertSeeHtml('data-generation-activity="idle"');
});

// --- Fenetre de lancement -------------------------------------------------------------------------

it('renders the launch button without any query, and reads the settings and the spend of the month only when the window opens', function () {
    BlogAi::saveSettings(['image_mode' => 'generated', 'max_images' => 3]);

    DB::enableQueryLog();
    $launcher = Livewire::test(GenerationLauncher::class);

    expect(count(DB::getQueryLog()))->toBe(0);
    // Le mode d'essai ne se dit plus dans l'en-tete : une ligne neutre de l'etat du service suffit.
    $launcher->assertSee('Générer un article')->assertDontSee('Mode d\'essai')->assertSet('imageMode', '');

    $launcher->call('prepare')
        ->assertSet('imageMode', 'generated')
        ->assertSet('maxImages', '3')
        // Une seconde ouverture garde ce que la personne a deja choisi.
        ->set('imageMode', 'none')
        ->call('prepare')
        ->assertSet('imageMode', 'none');

    expect(count(DB::getQueryLog()))->toBe(2);
});

it('shows the estimated cost of the article in the launch window, and follows its length', function () {
    $launcher = Livewire::test(GenerationLauncher::class)
        ->call('prepare')
        ->assertSet('articleWords', '1000')
        ->assertSeeHtml('data-forecast-range')
        ->assertSee('Entre ')
        ->assertDontSeeHtml('data-forecast-above-cap');

    $short = $launcher->viewData('forecast')['high'];
    $launcher->set('articleWords', '2000');

    expect($launcher->viewData('forecast')['high'])->not->toBe($short);
    expect($launcher->html())->not->toMatch('/<button(?=[^>]*data-confirm-launch)(?=[^>]*\sdisabled[\s>])[^>]*>/');
});

it('disables the launch button when the article could cost more than the cap per article, and refuses it on the server too', function () {
    BlogAi::saveSettings(['article_spend_cap_cents' => 1]);

    $launcher = Livewire::test(GenerationLauncher::class)
        ->call('prepare')
        ->assertSeeHtml('data-forecast-above-cap')
        ->assertSee('Si même 400 mots ne passent pas');

    expect($launcher->html())->toMatch('/<button(?=[^>]*data-confirm-launch)(?=[^>]*\sdisabled[\s>])[^>]*>/');

    // Le bouton desactive ne suffit pas : l'appel direct est refuse, et la raison est dite.
    $launcher->call('launch')
        ->assertDispatched('ui-toast', type: 'error')
        ->assertNoRedirect();

    expect(BlogGeneration::count())->toBe(0);
});

it('queues a generation with its topic, its instructions and its own images, then opens its page', function () {
    $user = User::factory()->create();
    actingAs($user);
    BlogAi::saveSettings(['image_mode' => 'stock', 'max_images' => 1]);

    $launcher = Livewire::test(GenerationLauncher::class)
        ->call('prepare')
        ->set('topicHint', 'Labels on Etsy')
        ->set('instructions', 'Short, for beginners.')
        ->set('imageMode', 'generated')
        ->set('maxImages', '2')
        ->call('launch')
        ->assertHasNoErrors();

    $generation = BlogGeneration::query()->sole();

    $launcher->assertRedirect(route('admin.blog.generation.show', ['generation' => $generation->id]));

    expect($generation->status)->toBe(BlogGenerationStatus::Queued)
        ->and($generation->topic_hint)->toBe('Labels on Etsy')
        ->and($generation->instructions)->toBe('Short, for beginners.')
        ->and($generation->requested_by)->toBe($user->id)
        // Les images sont celles de cette generation, pas celles des reglages ; la longueur, celle des reglages.
        ->and($generation->payload)->toBe(['images' => ['mode' => 'generated', 'max' => 2], 'length' => ['words' => 1_000]])
        ->and(session('toast-success'))->toBe('Génération lancée.');
});

it('refuses a topic, instructions or images that make no sense, in plain words', function () {
    Livewire::test(GenerationLauncher::class)
        ->call('prepare')
        ->set('topicHint', str_repeat('a', 301))
        ->set('instructions', str_repeat('a', 2_001))
        ->set('imageMode', 'hologram')
        ->set('maxImages', '4')
        ->call('launch')
        ->assertHasErrors(['topicHint', 'instructions', 'imageMode', 'maxImages'])
        ->assertSee('Le sujet ne doit pas dépasser 300 caractères.')
        ->assertNoRedirect();

    expect(BlogGeneration::count())->toBe(0);
});

it('warns as the spend of the month nears its cap, then passes it, and still launches', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    $spent = BlogGeneration::factory()->completed(costMicrousd: 850_000)->create();

    Livewire::test(GenerationDashboard::class)
        ->assertSee('La dépense du mois approche du plafond')
        ->assertSee('soit 85 % du plafond de 1,0000 $')
        ->assertSeeHtml('data-monthly-warning="near"')
        ->assertSeeHtml('ui:bg-amber-500');

    // Decision de Micha du 17/11 : au-dela du plafond, tout continue, la page le dit.
    $spent->update(['cost_microusd' => 1_200_000]);

    Livewire::test(GenerationDashboard::class)
        ->assertSee('Le plafond de dépense du mois est dépassé')
        ->assertSee('Les générations continuent, car ce plafond ne fait qu\'avertir.')
        ->assertSeeHtml('ui:bg-red-500');

    Livewire::test(GenerationLauncher::class)
        ->call('prepare')
        ->assertSeeHtml('data-monthly-warning="over"')
        ->call('launch')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(BlogGeneration::count())->toBe(2);
});

// --- Historique -----------------------------------------------------------------------------------

it('lists every generation in the history, newest first, twenty per page, in 3 queries', function () {
    BlogGeneration::factory()->count(22)->completed()->create();
    $failed = BlogGeneration::factory()->failed()->create(['topic_hint' => 'Labels on Etsy']);
    BlogGenerationStep::factory()->for($failed, 'generation')->named('research', 2)->failed()->create();
    $waiting = BlogGeneration::factory()->scheduled()->create();

    DB::enableQueryLog();
    $history = Livewire::test(GenerationHistory::class);

    // Nombre, page, etape en echec des generations de la page.
    expect(count(DB::getQueryLog()))->toBe(3)
        ->and(substr_count($history->html(), 'data-generation="'))->toBe(20);

    $history->assertSeeInOrder(['Sujet choisi par l\'IA', 'Labels on Etsy', 'Échec à l\'étape « Dossier de faits »'])
        ->assertSee('Automatique')
        ->assertSeeHtml('wire:click="retry('.$failed->id.')"')
        ->assertSeeHtml('pending = '.$waiting->id)
        // Modeles et tokens se lisent sur la page de la generation, plus dans la liste.
        ->assertDontSee('Modèles')
        ->assertDontSee('Jetons')
        ->assertDontSee('Simulated provider failure.');
});

it('restarts a failed generation and stops a waiting one from the history, and keeps who stopped it', function () {
    $user = User::factory()->create();
    actingAs($user);
    $failed = BlogGeneration::factory()->failed()->create();
    $waiting = BlogGeneration::factory()->create();
    $running = BlogGeneration::factory()->running()->create();

    Livewire::test(GenerationHistory::class)
        ->call('retry', $failed->id)
        ->assertDispatched('ui-toast', type: 'success', title: 'Génération relancée.')
        ->call('cancel', $waiting->id)
        ->assertDispatched('ui-toast', type: 'success', title: 'Génération arrêtée.')
        ->call('cancel', $running->id)
        ->assertDispatched('ui-toast', type: 'success', title: 'Génération arrêtée.');

    // Une generation deja commencee s'arrete aussi tout de suite, sans attendre la fin de son etape.
    expect($failed->refresh()->status)->toBe(BlogGenerationStatus::Queued)
        ->and($waiting->refresh()->status)->toBe(BlogGenerationStatus::Cancelled)
        ->and($waiting->cancel_requested_by)->toBe($user->id)
        ->and($waiting->cancel_requested_at)->not->toBeNull()
        ->and($running->refresh()->status)->toBe(BlogGenerationStatus::Cancelled)
        ->and($running->lock_owner)->toBeNull()
        ->and($running->cancel_requested_by)->toBe($user->id);
});

it('says so when the history is empty, in a single query', function () {
    DB::enableQueryLog();
    $history = Livewire::test(GenerationHistory::class);

    expect(count(DB::getQueryLog()))->toBe(1);
    $history->assertSee('Aucune génération pour le moment.');
});

it('shows a load failure instead of the history when the data cannot be read', function () {
    Log::spy();
    Schema::rename('blog_generations', 'blog_generations_unavailable');

    Livewire::test(GenerationHistory::class)->assertSee('Le chargement de l\'historique a échoué.');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'admin blog generation history'));

    Schema::rename('blog_generations_unavailable', 'blog_generations');
});

// --- Page d'une generation ------------------------------------------------------------------------

it('shows a generation step by step, with its model, tokens and cost under each step, in 2 queries', function () {
    $user = User::factory()->create(['name' => 'Alex Martin']);
    $generation = BlogGeneration::factory()->failed()->create([
        'topic_hint' => 'Labels on Etsy',
        'instructions' => 'Short.',
        'requested_by' => $user->id,
        'payload' => ['images' => ['mode' => 'stock', 'max' => 2], 'length' => ['words' => 1_200]],
        'cost_microusd' => 42_500,
        'last_error' => 'Provider is down.',
    ]);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed(30_000)->create([
        'model' => 'anthropic/claude-sonnet-5.5', 'web_searches' => 2, 'summary' => 'Sellers keep asking about labels.',
    ]);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('research', 2)->failed(attempt: 3)->create([
        'error' => 'Provider is down.', 'input_tokens' => 800, 'output_tokens' => 100, 'cost_microusd' => 12_500, 'cost_is_estimated' => true, 'duration_ms' => 4_000,
    ]);

    DB::enableQueryLog();
    $detail = Livewire::test(GenerationDetail::class, ['generation' => $generation->id]);

    // La generation avec le nom de qui l'a lancee et de qui l'a arretee (jointures), puis ses etapes.
    expect(count(DB::getQueryLog()))->toBe(2);

    $detail->assertSee('Génération n° '.$generation->id.'.')
        ->assertSee('Lancée par Alex Martin le '.$generation->created_at->format('d/m/Y à H:i').' (UTC)')
        ->assertSee('Labels on Etsy')
        ->assertSee('Short.')
        ->assertSee('Échec à l\'étape « Dossier de faits »')
        ->assertSee('Vous pouvez relancer la génération. Elle reprendra à l\'étape qui a échoué')
        ->assertSee('contactez l\'assistance technique')
        ->assertDontSee('Provider is down.')
        // Le total et le cout de chaque etape, a quatre decimales : 0,0300 $ + 0,0125 $ = 0,0425 $.
        ->assertSee('0,0425 $')
        ->assertSee('0,0300 $')
        ->assertSee('0,0125 $')
        ->assertSee('Banque d\'images, 2 au plus')
        ->assertSee('Environ 1 200 mots (7 200 caractères)')
        // Le modele sous son nom commercial, les tokens par sens avec le mot « tokens », plus aucune phrase « jetons lus ».
        ->assertSee('Claude Sonnet 5.5')
        ->assertDontSee('anthropic/claude-sonnet-5.5')
        ->assertSee('1 200 tokens envoyés au modèle, 400 tokens écrits par le modèle')
        ->assertSee('2 000 tokens envoyés au modèle, 500 tokens écrits par le modèle')
        ->assertDontSee('jetons')
        ->assertSee('2 recherches web')
        ->assertSee('essai 3')
        ->assertSee('estimé')
        ->assertSee('1 étape terminée sur 9')
        ->assertSee('Relancer')
        // Le compte rendu de l'IA, et celui de l'etape.
        ->assertSee('Compte rendu de l\'IA')
        ->assertSee('Pourquoi ce sujet')
        ->assertSee('Sellers keep asking about labels.')
        ->assertDontSee('Déclenchement')
        ->assertDontSeeHtml('ui-description-list')
        ->assertDontSeeHtml('wire:poll');
});

it('says who stopped a generation, when, and after which step', function () {
    $user = User::factory()->create(['name' => 'Alex Martin']);
    actingAs($user);
    $generation = BlogGeneration::factory()->running()->create(['requested_by' => $user->id]);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed()->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('research', 2)->completed()->create();

    $detail = Livewire::test(GenerationDetail::class, ['generation' => $generation->id])
        ->assertSeeHtml('wire:poll.2s')
        ->assertSee('Étape 3 sur 9')
        ->assertSee('Arrêter')
        ->call('cancel')
        ->assertDispatched('ui-toast', type: 'success', title: 'Génération arrêtée.');

    $generation->refresh();

    expect($generation->status)->toBe(BlogGenerationStatus::Cancelled)
        ->and($generation->cancel_requested_by)->toBe($user->id);

    // L'arret est immediat : l'ecran le dit aussitot, sans attendre de worker.
    $detail->assertSee('Arrêtée par Alex Martin le '.$generation->cancel_requested_at->format('d/m/Y à H:i').' (UTC), après l\'étape « Dossier de faits ».')
        ->assertSee('Aucun article n\'a été créé.')
        ->assertDontSee('Arrêtée au back-office')
        ->assertDontSeeHtml('wire:poll');
});

it('says a generation was stopped before its first step, and stays sober when nobody is known', function () {
    $waiting = BlogGeneration::factory()->cancelled()->create(['cancel_requested_at' => now()]);

    Livewire::test(GenerationDetail::class, ['generation' => $waiting->id])
        ->assertSee('Arrêtée le '.$waiting->cancel_requested_at->format('d/m/Y à H:i').' (UTC), avant sa première étape.')
        ->assertSee('Lancée à la main le');

    $scheduled = BlogGeneration::factory()->scheduled()->completed()->create();

    Livewire::test(GenerationDetail::class, ['generation' => $scheduled->id])
        ->assertSee('Lancée automatiquement le')
        // Une generation anterieure au choix des images par generation ne pretend rien.
        ->assertDontSee('Banque d\'images');
});

it('says a generation failed outside of any step, without a technical message', function () {
    $generation = BlogGeneration::factory()->failed()->create(['last_error' => 'Unknown pipeline [editorial].']);

    Livewire::test(GenerationDetail::class, ['generation' => $generation->id])
        ->assertSee('La génération a échoué')
        ->assertSee('contactez l\'assistance technique')
        ->assertDontSee('Unknown pipeline');
});

it('says where the credit ran out and what to do about it', function () {
    $generation = BlogGeneration::factory()->failed()->create(['outcome_reason' => BlogGenerationOutcomeReason::CreditExhausted]);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('writing', 3)->failed(attempt: 1)->create();

    Livewire::test(GenerationDetail::class, ['generation' => $generation->id])
        ->assertSee('Crédit épuisé chez le fournisseur d\'IA, à l\'étape « Rédaction »')
        ->assertSee('Rechargez le crédit du compte chez le fournisseur d\'IA')
        ->assertDontSee('Simulated provider failure.');
});

it('restarts a failed generation from its page', function () {
    $generation = BlogGeneration::factory()->failed()->create();

    Livewire::test(GenerationDetail::class, ['generation' => $generation->id])
        ->call('retry')
        ->assertDispatched('ui-toast', type: 'success', title: 'Génération relancée.')
        ->assertSee('En attente')
        ->assertSee('Génération demandée, démarrage dans quelques secondes.')
        ->assertSeeHtml('wire:poll.2s');

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Queued);
});

it('says a generation cannot be found', function () {
    Livewire::test(GenerationDetail::class, ['generation' => 999_999])
        ->assertSee('Cette génération est introuvable.');
});

// --- Reglages --------------------------------------------------------------------------------------

it('opens the settings on their current values, in 2 queries with the spend of the month', function () {
    BlogAi::saveSettings([
        'enabled' => true, 'period_count' => 2, 'period_unit' => 'month', 'monthly_spend_cap_cents' => 2_550, 'article_spend_cap_cents' => 20,
        'priority_topics' => ['Etsy', 'Amazon'], 'notification_email' => 'owner@example.com',
    ]);

    DB::enableQueryLog();
    $settings = Livewire::test(GenerationSettings::class);

    expect(count(DB::getQueryLog()))->toBe(2);
    $settings->assertSet('enabled', true)
        ->assertSet('periodCount', '2')
        ->assertSet('periodUnit', 'month')
        ->assertSet('monthlySpendCap', '25.50')
        ->assertSet('articleSpendCap', '0.20')
        ->assertSet('priorityTopics', ['Etsy', 'Amazon'])
        ->assertSet('notificationEmail', 'owner@example.com')
        ->assertSet('models.writing', config('blog.ai.defaults.models.writing'))
        ->assertSee('En français')
        // Un modele se choisit dans une liste, sous son nom commercial, avec son prix indicatif.
        ->assertSee('Claude Sonnet 5.5 (Anthropic) · 2 $ / 10 $')
        ->assertSee('Claude Haiku 5.5 (Anthropic) · 0,10 $ / 0,50 $')
        ->assertSee('Nano Banana 2 (Google)')
        ->assertDontSeeHtml('<datalist')
        // Ce qui relève du developpeur ne s'affiche pas a la personne qui regle.
        ->assertDontSee('se règle dans la configuration du site')
        ->assertDontSee('à raison d\'un sujet par ligne');
});

it('offers only image models for the image step, and only text models for the others', function () {
    $html = Livewire::test(GenerationSettings::class)->html();

    $select = fn (string $step): string => (string) str($html)->after('id="ai-model-'.$step.'"')->before('</select>');

    expect($select('image'))->toContain('google/gemini-3.1-flash-image')->toContain('openai/gpt-5.4-image-2')->not->toContain('anthropic/claude-sonnet-5.5')
        ->and($select('writing'))->toContain('anthropic/claude-sonnet-5.5')->not->toContain('google/gemini-3.1-flash-image');
});

it('saves the settings', function () {
    Livewire::test(GenerationSettings::class)
        ->set('enabled', true)
        ->set('periodCount', '3')
        ->set('periodUnit', 'day')
        ->set('articlesPerRun', '2')
        ->set('pendingDraftsCap', '5')
        ->set('monthlySpendCap', '12.5')
        ->set('articleSpendCap', '0.08')
        ->set('priorityTopics', ['Etsy', ' Amazon ', 'etsy', ''])
        ->set('avoidedTopics', ['Crypto'])
        ->set('models.writing', 'openai/gpt-6.1-terra')
        ->set('models.image', 'openai/gpt-5.4-image-2')
        ->set('imageMode', 'generated')
        ->set('maxImages', '3')
        ->set('articleWords', '1500')
        ->set('notificationEmail', 'owner@example.com')
        ->set('transparencyNotice.fr', 'Article rédigé avec l\'aide d\'une IA.')
        ->call('save')
        ->assertHasNoErrors()
        // La liste revient telle qu'elle est enregistree : sans vide ni doublon.
        ->assertSet('priorityTopics', ['Etsy', 'Amazon'])
        ->assertDispatched('ui-toast', type: 'success', title: 'Réglages enregistrés.');

    $saved = app(BlogAiSettingsService::class)->current();

    expect($saved->enabled)->toBeTrue()
        ->and($saved->periodCount)->toBe(3)
        ->and($saved->periodUnit)->toBe(BlogAiPeriodUnit::Day)
        ->and($saved->articlesPerRun)->toBe(2)
        ->and($saved->pendingDraftsCap)->toBe(5)
        ->and($saved->monthlySpendCapCents)->toBe(1_250)
        ->and($saved->articleSpendCapCents)->toBe(8)
        ->and($saved->priorityTopics)->toBe(['Etsy', 'Amazon'])
        ->and($saved->avoidedTopics)->toBe(['Crypto'])
        ->and($saved->modelFor('writing'))->toBe('openai/gpt-6.1-terra')
        ->and($saved->modelFor('image'))->toBe('openai/gpt-5.4-image-2')
        ->and($saved->modelFor('topic'))->toBe(config('blog.ai.defaults.models.topic'))
        ->and($saved->imageMode)->toBe(BlogAiImageMode::Generated)
        ->and($saved->maxImages)->toBe(3)
        ->and($saved->articleWords)->toBe(1_500)
        ->and($saved->notificationEmail)->toBe('owner@example.com')
        ->and($saved->transparencyNotice['fr'])->toBe('Article rédigé avec l\'aide d\'une IA.');
});

it('shows the estimated cost of an article in the settings, warns as soon as it goes above the cap, and still saves', function () {
    // Decision de Micha du 18/11 : la fourchette se dit estimation, et la limite garantie (plafond de 0,15 $
    // par defaut, plus 10 %) est affichee.
    $settings = Livewire::test(GenerationSettings::class)
        ->assertSee('Coût estimé d\'un article')
        ->assertSeeHtml('data-forecast-range')
        ->assertSee('Estimation tirée des articles déjà générés. Le coût réel peut en sortir.')
        ->assertSee('Un article ne dépasse jamais 0,1650 $, soit ce plafond augmenté de 10 %.')
        ->assertDontSeeHtml('data-forecast-above-cap');

    // Les valeurs a l'ecran comptent avant l'enregistrement : un plafond baisse fait paraitre l'alerte.
    $settings->set('articleSpendCap', '0.01')
        ->assertSeeHtml('data-forecast-above-cap')
        ->assertSee('aucun article ne partira, ni à la main ni automatiquement')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(BlogAiSettingsService::class)->current()->articleSpendCapCents)->toBe(1);
});

it('warns next to the monthly cap in the settings, following the cap typed, without blocking anything', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    BlogGeneration::factory()->completed(costMicrousd: 850_000)->create();

    $settings = Livewire::test(GenerationSettings::class)
        ->assertSee('Ce plafond ne bloque rien. Vous êtes averti à l\'écran et par e-mail quand la dépense du mois en atteint 80 %')
        ->assertSeeHtml('data-monthly-warning="near"');

    $settings->set('monthlySpendCap', '0.50')->assertSeeHtml('data-monthly-warning="over"');
    $settings->set('monthlySpendCap', '5')->assertDontSeeHtml('data-monthly-warning');
});

it('refuses settings that make no sense, each with its own message', function () {
    Livewire::test(GenerationSettings::class)
        ->set('periodCount', '0')
        ->set('periodUnit', 'century')
        ->set('articlesPerRun', '11')
        ->set('pendingDraftsCap', '')
        ->set('monthlySpendCap', '-1')
        ->set('articleSpendCap', '0')
        // La liste des modeles est fermee : ni identifiant libre, ni modele de texte pour l'etape des images.
        ->set('models.writing', 'someone/unknown-model')
        ->set('models.image', 'anthropic/claude-sonnet-5.5')
        ->set('priorityTopics', [str_repeat('a', 121)])
        ->set('imageMode', 'hologram')
        ->set('maxImages', '4')
        ->set('articleWords', '2500')
        ->set('notificationEmail', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['periodCount', 'periodUnit', 'articlesPerRun', 'pendingDraftsCap', 'monthlySpendCap', 'articleSpendCap', 'models.writing', 'models.image', 'priorityTopics.0', 'imageMode', 'maxImages', 'articleWords', 'notificationEmail'])
        ->assertSee('Le texte compte 2 000 mots au plus.')
        ->assertSee('La période est d\'au moins 1.')
        ->assertSee('Un lot compte 10 articles au plus.')
        ->assertSee('Le plafond de dépense ne peut pas être négatif.')
        ->assertSee('Le plafond par article est d\'au moins 0,01 $.')
        ->assertSee('Choisissez un modèle dans la liste.')
        ->assertSee('Un sujet ne doit pas dépasser 120 caractères.')
        ->assertSee('Cette adresse e-mail n\'est pas valide.')
        ->assertNotDispatched('ui-toast');

    expect(app(BlogAiSettingsService::class)->current()->enabled)->toBeFalse();
});

it('keeps a saved model that left the list visible, so that another one gets chosen', function () {
    Setting::query()->updateOrCreate(['key' => BlogAiSettingsService::KEY], ['value' => json_encode(['models' => ['writing' => 'old/retired-model']])]);

    Livewire::test(GenerationSettings::class)
        ->assertSet('models.writing', 'old/retired-model')
        ->assertSee('old/retired-model (retiré de la liste)')
        ->call('save')
        ->assertHasErrors(['models.writing' => 'in']);
});
