<?php

use App\Data\Blog\Generation\GenerationCostForecast;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Enums\Blog\Generation\BlogEffortLevel;
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
use App\Services\Admin\Blog\Generation\BlogGenerationPresenter;
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
        ->assertDontSee('role="tablist"', false)
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
        ->assertSee(dollarsText('1,0000 $'))
        ->assertSee(dollarsText('sur 4,0000 $ de plafond'))
        ->assertSee('Activée')
        ->assertSee('2 articles chaque semaine')
        // Le titre « Brouillons à relire » dit deja ce qu'est le nombre : rien d'autre que le lien (Micha, 19/11).
        ->assertDontSee('attendent votre relecture')
        ->assertSee('Voir les brouillons')
        ->assertSeeHtml(e(route('admin.blog.posts.index', ['origin' => 'ai', 'status' => 'draft'])))
        // Ni formulaire de lancement ni journal pagine sur cette page : chacun a sa place.
        ->assertDontSee('Lancer une génération')
        ->assertDontSeeHtml('ui-pagination');
});

it('shows the automatic generation as paused while the drafts to review fill their cap', function () {
    BlogAi::saveSettings(['enabled' => true, 'pending_drafts_cap' => 2]);
    BlogPost::factory()->count(2)->create(['origin' => BlogPostOrigin::Ai]);

    Livewire::test(GenerationDashboard::class)
        ->assertSee('En pause')
        ->assertSee('Reprend quand les brouillons sont relus')
        ->assertDontSee('Activée');
});

it('says when a worker is listening, and warns when none is', function () {
    // Dit avec les mots de la personne qui ecrit des articles : ni « traitement », ni « a l'ecoute ».
    Livewire::test(GenerationDashboard::class)
        ->assertSee('Les générations ne démarrent pas pour le moment')
        ->assertSee('Un article demandé maintenant restera en attente.')
        ->assertSee('Si cela persiste, contactez l\'assistance technique.')
        ->assertDontSee('Si cela dure')
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
        ->assertSee(dollarsText('0,2625 $'))
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

it('loads the overview in 7 queries, plus 1 for the activity tile, whatever the number of generations', function () {
    BlogGeneration::factory()->count(40)->completed()->create()->each(function (BlogGeneration $generation): void {
        BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed()->create();
        BlogGenerationStep::factory()->for($generation, 'generation')->named('writing', 2)->completed()->create();
    });

    DB::enableQueryLog();
    Livewire::test(GenerationDashboard::class);

    // Reglages, depense du mois, worker, verification quotidienne des services (controle du 20/11), brouillons a
    // relire, dernieres generations, etape en echec de celles-ci ; plus la tuile « En cours » (une requete :
    // aucune generation active). Le bouton de lancement n'en fait aucune.
    expect(count(DB::getQueryLog()))->toBe(8);
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

it('renders the launch button without any query, and reads the settings, the spend of the month and the prices of the day only when the window opens', function () {
    BlogAi::saveSettings(['image_mode' => 'generated', 'max_images' => 3, 'effort' => 'high']);

    DB::enableQueryLog();
    $launcher = Livewire::test(GenerationLauncher::class);

    expect(count(DB::getQueryLog()))->toBe(0);
    // Le mode d'essai ne se dit plus dans l'en-tete : une ligne neutre de l'etat du service suffit.
    $launcher->assertSee('Générer un article')->assertDontSee('Mode d\'essai')->assertSet('imageMode', '');

    $launcher->call('prepare')
        ->assertSet('imageMode', 'generated')
        ->assertSet('maxImages', '3')
        ->assertSet('effort', 'high')
        // Une seconde ouverture garde ce que la personne a deja choisi.
        ->set('imageMode', 'none')
        ->call('prepare')
        ->assertSet('imageMode', 'none');

    // Reglages, depense du mois, et prix du jour des modeles pour l'estimation (decision du 23/11).
    expect(count(DB::getQueryLog()))->toBe(3);
});

it('opens a launch window wide enough for its fields', function () {
    // La largeur ordinaire d'une fenetre du kit serrait les champs (Micha, 19/11).
    expect(Livewire::test(GenerationLauncher::class)->html())->toContain('max-width: 45em');
});

it('offers the effort level in the launch window, follows it in the estimate, and keeps it on the generation', function () {
    // Decision de Micha du 19/11 : le niveau des reglages, modifiable pour chaque generation.
    $launcher = Livewire::test(GenerationLauncher::class)
        ->call('prepare')
        ->assertSet('effort', 'medium')
        ->assertSeeHtml('data-effort-field')
        ->assertSee('Moyen (recommandé)')
        ->assertSee('Élevé lit plus de sources et réfléchit davantage, pour un coût plus élevé.');

    $medium = $launcher->viewData('forecast')['high'];
    $launcher->set('effort', 'high');

    expect($launcher->viewData('forecast')['high'])->not->toBe($medium);

    $launcher->call('launch')->assertHasNoErrors();

    expect(BlogGeneration::query()->sole()->payload['effort'])->toBe('high');
    Livewire::test(GenerationLauncher::class)->call('prepare')->set('effort', 'maximum')->call('launch')
        ->assertHasErrors(['effort'])
        ->assertSee('Choisissez le niveau d\'effort.');
});

it('shows the estimated cost of the article in the launch window, and follows its length', function () {
    $launcher = Livewire::test(GenerationLauncher::class)
        ->call('prepare')
        ->assertSet('articleWords', '1000')
        ->assertSeeHtml('data-forecast-range')
        ->assertSee('Entre ')
        ->assertSeeHtml('data-forecast-stop');

    $short = $launcher->viewData('forecast')['high'];
    $launcher->set('articleWords', '2000');

    expect($launcher->viewData('forecast')['high'])->not->toBe($short);
    expect($launcher->html())->not->toMatch('/<button(?=[^>]*data-confirm-launch)(?=[^>]*\sdisabled[\s>])[^>]*>/');
});

it('launches whatever the estimated cost, and shows the safety stop next to the estimate', function () {
    // Decision de Micha du 18/11 : plus de plafond par article. Sonnet et trois images generees partent aussi.
    BlogAi::saveSettings(['models' => ['writing' => 'anthropic/claude-sonnet-5.5'], 'image_mode' => 'generated', 'max_images' => 3]);

    $launcher = Livewire::test(GenerationLauncher::class)
        ->call('prepare')
        ->assertSeeHtml('data-forecast-stop')
        ->assertSee('Arrêt de sécurité');

    expect($launcher->html())->not->toMatch('/<button(?=[^>]*data-confirm-launch)(?=[^>]*\sdisabled[\s>])[^>]*>/');

    $launcher->call('launch')->assertHasNoErrors();

    expect(BlogGeneration::query()->sole()->forecast_high_microusd)->toBeGreaterThan(0);
});

it('shows the cost announced at launch under the real cost of a generation, and nothing for an older one', function () {
    $announced = BlogGeneration::factory()->completed(costMicrousd: 61_000)->create(['forecast_low_microusd' => 55_000, 'forecast_high_microusd' => 90_000]);
    $older = BlogGeneration::factory()->completed(costMicrousd: 61_000)->create(['forecast_low_microusd' => null, 'forecast_high_microusd' => null]);

    Livewire::test(GenerationDetail::class, ['generation' => $announced->id])
        ->assertSee(dollarsText('0,0610 $'))
        ->assertSee(dollarsText('Annoncé entre 0,0550 $ et 0,0900 $'));
    Livewire::test(GenerationDetail::class, ['generation' => $older->id])
        ->assertDontSeeHtml('data-generation-announced');
});

it('shows the few attention points of a finished generation under their French heading, and nothing of what was removed', function () {
    // Decision de Micha du 19/11 : ce que la verification a retire ou corrige est regle et ne s'affiche plus ; seuls
    // les rares doutes restants, en francais, situes dans le brouillon francais.
    $finished = BlogGeneration::factory()->completed(costMicrousd: 60_000)->create();
    BlogGenerationStep::factory()->for($finished, 'generation')->named('verification', 6)->completed(4_000)->create([
        'output' => [
            'article' => ['body' => "Intro.\n\n## Who signs the declaration\n\nText.\n\n### On Etsy\n\nMore."],
            'removed_claims' => [['claim' => 'The box is read before paying.', 'reason' => "L'acheteur en ligne ne voit pas la boîte."]],
            'attention_points' => [
                ['heading' => '', 'statement_fr' => 'Le règlement s\'applique depuis décembre 2024.', 'check_fr' => 'Vérifiez la date d\'application.'],
                ['heading' => '## On Etsy', 'statement_fr' => 'Etsy demande l\'adresse de la personne responsable.', 'check_fr' => 'Vérifiez la page d\'aide d\'Etsy.'],
                ['heading' => 'A heading the article does not have', 'statement_fr' => 'Une affirmation sans lieu.', 'check_fr' => 'Vérifiez la source.'],
            ],
        ],
    ]);
    BlogGenerationStep::factory()->for($finished, 'generation')->named('version_check_fr', 9)->completed(1_000)->create([
        'output' => [
            'article' => ['body' => "Intro.\n\n## Qui signe la déclaration\n\nTexte.\n\n### Sur Etsy\n\nSuite."],
            'corrections' => [['original' => 'Il faut les deux.', 'corrected' => 'Il faut l\'un des deux.', 'reason' => 'La version fait une obligation double.']],
        ],
    ]);

    $detail = Livewire::test(GenerationDetail::class, ['generation' => $finished->id])
        ->assertSeeHtml('data-attention-points')
        ->assertSeeInOrder([
            'Points d\'attention', 'Des affirmations gardées dans l\'article, sur lesquelles la vérification a un doute.',
            'Dans l\'introduction', 'Le règlement s\'applique depuis décembre 2024.', 'Vérifiez la date d\'application.',
            'Sous l\'intertitre « Sur Etsy »', 'Etsy demande l\'adresse de la personne responsable.',
            'Une affirmation sans lieu.',
        ])
        ->assertDontSee('À relire en priorité')
        ->assertDontSee('Texte anglais')
        ->assertDontSee('The box is read before paying.')
        ->assertDontSee('Il faut les deux.')
        ->assertDontSee('On Etsy');

    // Un intertitre introuvable ne donne aucun lieu, jamais un lieu en anglais.
    expect(substr_count($detail->html(), 'data-attention-point>'))->toBe(3)
        ->and(substr_count($detail->html(), 'intertitre'))->toBe(1);
});

it('names the first step after what it looks for, lasting rules as well as news', function () {
    // Decision de Micha du 19/11 : la veille ne contraint pas a l'actualite, et son nom suit.
    $generation = BlogGeneration::factory()->completed()->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic_scan', 1)->completed(1_000)->create();

    Livewire::test(GenerationDetail::class, ['generation' => $generation->id])
        ->assertSee('Recherche de pistes')
        ->assertDontSee('Veille de l\'actualité');
});

it('shows no attention points while a generation runs, nor when the verification has none', function () {
    $running = BlogGeneration::factory()->running()->create();
    BlogGenerationStep::factory()->for($running, 'generation')->named('verification', 6)->completed(4_000)->create([
        'output' => ['attention_points' => [['heading' => '', 'statement_fr' => 'Pas encore affiché.', 'check_fr' => 'x']]],
    ]);
    $clean = BlogGeneration::factory()->completed()->create();
    BlogGenerationStep::factory()->for($clean, 'generation')->named('verification', 6)->completed(4_000)->create([
        'output' => ['article' => ['body' => 'Text.'], 'removed_claims' => [['claim' => 'Removed.', 'reason' => 'x']], 'attention_points' => []],
    ]);

    Livewire::test(GenerationDetail::class, ['generation' => $running->id])
        ->assertDontSeeHtml('data-attention-points')
        ->assertDontSee('Pas encore affiché.');
    Livewire::test(GenerationDetail::class, ['generation' => $clean->id])
        ->assertDontSeeHtml('data-attention-points')
        ->assertDontSee('Points d\'attention');
});

it('puts the safety stop at twice the high of the estimate', function () {
    expect(app(BlogGenerationPresenter::class)->forecast(new GenerationCostForecast(50_000, 80_000)))->toBe([
        'low' => dollarsText('0,0500 $'),
        'high' => dollarsText('0,0800 $'),
        'stop' => dollarsText('0,1600 $'),
    ]);
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
        ->and($generation->payload)->toBe(['images' => ['mode' => 'generated', 'max' => 2], 'effort' => 'medium', 'models' => config('blog.ai.defaults.models'), 'length' => ['words' => 1_000]])
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
        ->assertSee(dollarsText('soit 85 % du plafond de 1,0000 $'))
        // Decision de Micha du 19/11 : une phrase simple, a la place de « ce plafond ne fait qu'avertir ».
        ->assertSee('Dépasser le plafond ne bloque pas la génération.')
        ->assertDontSee('ne fait qu\'avertir')
        ->assertSeeHtml('data-monthly-warning="near"')
        ->assertSeeHtml('ui:bg-amber-500');

    // Decision de Micha du 17/11 : au-dela du plafond, tout continue, la page le dit.
    $spent->update(['cost_microusd' => 1_200_000]);

    Livewire::test(GenerationDashboard::class)
        ->assertSee('Le plafond de dépense du mois est dépassé')
        ->assertSee('Ce dépassement ne bloque pas la génération. Si cette dépense est prévue, vous pouvez relever le plafond.')
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

it('shows the history as rows like the overview, with the duration and small icon actions in a tooltip, and no table', function () {
    $post = BlogPost::factory()->create();
    BlogGeneration::factory()->completed()->create(['blog_post_id' => $post->id, 'started_at' => now()->subMinutes(5)->subSeconds(19), 'finished_at' => now()]);
    BlogGeneration::factory()->failed()->create();
    BlogGeneration::factory()->create();

    $html = Livewire::test(GenerationHistory::class)->html();

    expect($html)->toContain('data-generation-history')
        ->not->toContain('ui-table')
        ->toContain('5 min 19 s')
        ->toContain('aria-label="Ouvrir le brouillon"')
        ->toContain('aria-label="Relancer"')
        ->toContain('aria-label="Arrêter"')
        ->toContain('ui-tooltip');
});

it('names a generation that created an article by the French title of the article, in the lists and on its page', function () {
    $post = BlogPost::factory()->withTranslations()->create();
    $post->translations()->where('locale', 'fr')->update(['title' => 'Bougies : les règles à connaître']);
    $generation = BlogGeneration::factory()->completed()->create(['topic_title' => 'Candle rules in English', 'blog_post_id' => $post->id]);
    BlogGeneration::factory()->completed()->create(['topic_title' => 'Working subject only']);

    Livewire::test(GenerationHistory::class)
        ->assertSee('Bougies : les règles à connaître')
        ->assertDontSee('Candle rules in English')
        // Sans article, le sujet de travail reste.
        ->assertSee('Working subject only');
    Livewire::test(GenerationDashboard::class)->assertSee('Bougies : les règles à connaître')->assertDontSee('Candle rules in English');
    Livewire::test(GenerationDetail::class, ['generation' => $generation->id])->assertSee('Bougies : les règles à connaître');
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

it('shows a generation step by step, with its model, tokens and cost under each step, in 3 queries', function () {
    $user = User::factory()->create(['name' => 'Alex Martin']);
    $generation = BlogGeneration::factory()->failed()->create([
        'topic_hint' => 'Labels on Etsy',
        'instructions' => 'Short.',
        'requested_by' => $user->id,
        'payload' => ['images' => ['mode' => 'stock', 'max' => 2], 'length' => ['words' => 1_200], 'effort' => 'high'],
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

    // La generation avec le nom de qui l'a lancee et de qui l'a arretee (jointures), ses etapes, puis, la generation
    // etant terminee, les passages que les controles ont retires ou corriges.
    expect(count(DB::getQueryLog()))->toBe(3);

    $detail->assertSee('Génération n° '.$generation->id.'.')
        ->assertSee('Lancée par Alex Martin le '.$generation->created_at->format('d/m/Y à H:i').' (UTC)')
        ->assertSee('Labels on Etsy')
        ->assertSee('Short.')
        ->assertSee('Échec à l\'étape « Dossier de faits »')
        ->assertSee('Vous pouvez relancer la génération. Elle reprendra à l\'étape qui a échoué')
        ->assertSee('contactez l\'assistance technique')
        ->assertDontSee('Provider is down.')
        // Le total et le cout de chaque etape, a quatre decimales : 0,0300 $ + 0,0125 $ = 0,0425 $.
        ->assertSee(dollarsText('0,0425 $'))
        ->assertSee(dollarsText('0,0300 $'))
        ->assertSee(dollarsText('0,0125 $'))
        ->assertSee('Banque d\'images, 2 au plus')
        ->assertSee('Environ 1 200 mots (7 200 caractères)')
        ->assertSeeInOrder(['Effort', 'Élevé'])
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

it('opens the settings on their current values, in 3 queries with the spend of the month and the prices of the day', function () {
    BlogAi::saveSettings([
        'enabled' => true, 'period_count' => 2, 'period_unit' => 'month', 'monthly_spend_cap_cents' => 2_550,
        'notification_email' => 'owner@example.com',
    ]);

    DB::enableQueryLog();
    $settings = Livewire::test(GenerationSettings::class);

    expect(count(DB::getQueryLog()))->toBe(3);
    $settings->assertSet('enabled', true)
        ->assertSet('periodCount', '2')
        ->assertSet('periodUnit', 'month')
        ->assertSet('monthlySpendCap', '25.50')
        ->assertSet('notificationEmail', 'owner@example.com')
        // Moyen au depart (decision de Micha du 19/11).
        ->assertSet('effort', 'medium')
        ->assertSet('models.writing', config('blog.ai.defaults.models.writing'))
        // Un modele se choisit dans une liste, sous son nom commercial, avec son prix indicatif.
        ->assertSee('Claude Sonnet 5.5 (Anthropic) · 2 $ / 10 $')
        ->assertSee('Claude Haiku 5.5 (Anthropic) · 0,10 $ / 0,50 $')
        ->assertSee('Nano Banana 2 (Google)')
        ->assertDontSeeHtml('<datalist')
        // Ce qui relève du developpeur ne s'affiche pas a la personne qui regle.
        ->assertDontSee('se règle dans la configuration du site');
});

it('lays the settings out in six sections in the order of use, the automatic generation last, and offers the length as a list of steps', function () {
    // Decision de Micha du 19/11 : une page qui se lit comme un recit, sans titres de groupe, sans les sujets, sans
    // la mention de transparence, et sans le mot « lot ».
    $settings = Livewire::test(GenerationSettings::class)
        ->assertSeeInOrder([
            'Modèles', 'Longueur, images et effort', 'Les valeurs que propose la fenêtre « Générer un article ».', 'Niveau d\'effort', 'Coût estimé d\'un article',
            'Budget du mois', 'E-mails', 'Génération automatique', 'Elle reprend les modèles, la longueur et les images réglés plus haut.',
            'Nombre d\'articles', 'Tous les', 'Unité', 'Brouillons en attente, au plus',
        ])
        ->assertSeeHtml('<option value="400"')
        ->assertSeeHtml('<option value="2000"')
        ->assertSee(dollarsText('1 000 mots, environ 6 000 caractères'))
        ->assertDontSeeHtml('id="ai-article-words" type="number"')
        ->assertDontSeeHtml('data-settings-group')
        ->assertDontSee('Sujets')
        ->assertDontSee('Mention de transparence');

    expect(preg_match('/\blots?\b/u', strip_tags($settings->html())))->toBe(0);
});

it('shows the rhythm of the automatic generation only once it is enabled, and keeps a refused field in sight', function () {
    $automaticFields = fn ($settings): string => (string) str($settings->html())->after('data-automatic-fields')->before('>');

    // Masques des le premier affichage tant que la case est decochee, puis lies a la case dans le navigateur.
    expect($automaticFields(Livewire::test(GenerationSettings::class)))->toContain('x-show="$wire.enabled || false"')->toContain('x-cloak');

    BlogAi::saveSettings(['enabled' => true]);
    expect($automaticFields(Livewire::test(GenerationSettings::class)))->not->toContain('x-cloak');

    $refused = Livewire::test(GenerationSettings::class)->set('enabled', false)->set('articlesPerRun', '11')->call('save')->assertHasErrors(['articlesPerRun']);
    expect($automaticFields($refused))->toContain('x-show="$wire.enabled || true"')->not->toContain('x-cloak');
});

it('puts the recommended models back in the lists without saving them, and follows our recommendations', function () {
    BlogAi::saveSettings(['models' => ['writing' => 'anthropic/claude-sonnet-5.5']]);

    // Un simple lien « Réinitialiser » sous les listes, seulement quand un modele differe (Micha, 20/11).
    $settings = Livewire::test(GenerationSettings::class)
        ->assertSeeHtml('data-restore-recommended-models')
        ->assertSeeText('Réinitialiser')
        ->assertDontSeeText('Remettre les modèles recommandés');

    // La mention en haut de la section suffit : les listes des modeles ne repetent pas « (recommandé) » (Micha, 19/11).
    $modelLists = collect(array_keys(config('blog.ai.defaults.models')))
        ->map(fn (string $step): string => (string) str($settings->html())->after('id="ai-model-'.$step.'"')->before('</select>'))
        ->implode('');
    expect($modelLists)->not->toContain('(recommandé)');

    $settings->call('restoreRecommendedModels')
        ->assertSet('models.writing', config('blog.ai.defaults.models.writing'))
        ->assertDispatched('ui-toast', type: 'info', title: 'Modèles réinitialisés. Enregistrez les réglages pour les garder.')
        ->assertDontSeeHtml('data-restore-recommended-models')
        ->assertDontSeeText('Modèles recommandés');

    // Rien n'est garde avant « Enregistrer les reglages ».
    expect(app(BlogAiSettingsService::class)->current()->modelFor('writing'))->toBe('anthropic/claude-sonnet-5.5');

    // Une recommandation changee a son seul endroit, la configuration, est suivie par le bouton.
    config(['blog.ai.defaults.models.verification' => 'anthropic/claude-haiku-5.5']);

    $settings->call('$refresh')
        ->assertSeeHtml('data-restore-recommended-models')
        ->call('restoreRecommendedModels')
        ->assertSet('models.verification', 'anthropic/claude-haiku-5.5')
        ->assertDontSeeHtml('data-restore-recommended-models');
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
        ->set('models.writing', 'openai/gpt-6.1-terra')
        ->set('models.image', 'openai/gpt-5.4-image-2')
        ->set('imageMode', 'generated')
        ->set('maxImages', '3')
        ->set('articleWords', '1600')
        ->set('effort', 'low')
        ->set('notificationEmail', 'owner@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('ui-toast', type: 'success', title: 'Réglages enregistrés.');

    $saved = app(BlogAiSettingsService::class)->current();

    expect($saved->enabled)->toBeTrue()
        ->and($saved->periodCount)->toBe(3)
        ->and($saved->periodUnit)->toBe(BlogAiPeriodUnit::Day)
        ->and($saved->articlesPerRun)->toBe(2)
        ->and($saved->pendingDraftsCap)->toBe(5)
        ->and($saved->monthlySpendCapCents)->toBe(1_250)
        ->and($saved->modelFor('writing'))->toBe('openai/gpt-6.1-terra')
        ->and($saved->modelFor('image'))->toBe('openai/gpt-5.4-image-2')
        ->and($saved->modelFor('topic'))->toBe(config('blog.ai.defaults.models.topic'))
        ->and($saved->imageMode)->toBe(BlogAiImageMode::Generated)
        ->and($saved->maxImages)->toBe(3)
        ->and($saved->articleWords)->toBe(1_600)
        ->and($saved->effort)->toBe(BlogEffortLevel::Low)
        ->and($saved->notificationEmail)->toBe('owner@example.com');
});

it('shows the estimated cost of an article and its safety stop in the settings, following the values on screen', function () {
    // Decision de Micha du 18/11 : la fourchette se dit estimation, et l'arret de securite est affiche a cote.
    $settings = Livewire::test(GenerationSettings::class)
        ->assertSee('Coût estimé d\'un article')
        ->assertSeeHtml('data-forecast-range')
        ->assertSee('Ceci est une estimation. Le coût réel peut varier.')
        ->assertSeeHtml('data-forecast-stop')
        ->assertDontSee('Plafond de dépense par article');

    // Les valeurs a l'ecran comptent avant l'enregistrement : un article plus long coute plus, un effort plus bas moins.
    $short = $settings->viewData('forecast')['stop'];
    $settings->set('articleWords', '2000');
    $long = $settings->viewData('forecast')['stop'];
    $settings->set('effort', 'low');

    expect($long)->not->toBe($short)
        ->and($settings->viewData('forecast')['stop'])->not->toBe($long);
});

it('warns next to the monthly cap in the settings, following the cap typed, without blocking anything', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    BlogGeneration::factory()->completed(costMicrousd: 850_000)->create();

    $settings = Livewire::test(GenerationSettings::class)
        ->assertSee('Dépasser le plafond ne bloque pas la génération. Vous êtes averti à l\'écran et par e-mail quand la dépense du mois en atteint 80 %')
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
        // La liste des modeles est fermee : ni identifiant libre, ni modele de texte pour l'etape des images.
        ->set('models.writing', 'someone/unknown-model')
        ->set('models.image', 'anthropic/claude-sonnet-5.5')
        ->set('imageMode', 'hologram')
        ->set('maxImages', '4')
        ->set('articleWords', '2500')
        ->set('notificationEmail', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['periodCount', 'periodUnit', 'articlesPerRun', 'pendingDraftsCap', 'monthlySpendCap', 'models.writing', 'models.image', 'imageMode', 'maxImages', 'articleWords', 'notificationEmail'])
        ->assertSee('Choisissez une longueur dans la liste.')
        ->assertSee('La période est d\'au moins 1.')
        ->assertSee('Le nombre d\'articles ne peut pas dépasser 10.')
        ->assertSee('Le plafond de dépense est d\'au moins 1 $.')
        ->assertSee('Choisissez un modèle dans la liste.')
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
