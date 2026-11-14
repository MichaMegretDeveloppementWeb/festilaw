<?php

use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Livewire\Admin\Blog\GenerationDashboard;
use App\Livewire\Admin\Blog\GenerationDetail;
use App\Livewire\Admin\Blog\GenerationProgress;
use App\Livewire\Admin\Blog\GenerationSettings;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
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
 | Ecrans de la generation par IA au back-office : tableau de bord (depense, lancement, journal), carte de
 | suivi en direct, detail d'une generation, reglages. Les etapes sont les etapes factices de la
 | configuration de test : aucun modele n'est appele.
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
    'dashboard' => ['/admin/blog/generation'],
    'settings' => ['/admin/blog/generation/settings'],
    'detail' => ['/admin/blog/generation/{generation}'],
]);

it('serves every generation screen to a signed-in admin, under its own menu entry', function () {
    $generation = BlogGeneration::factory()->completed()->create(['topic_hint' => 'Labels on Etsy']);
    actingAs(User::factory()->create());

    get('/admin/blog/generation')->assertOk()
        ->assertSee('<title>Génération IA · '.config('app.name').'</title>', false)
        ->assertSee('href="'.route('admin.blog.generation.index').'"', false)
        ->assertSee('Labels on Etsy');
    get('/admin/blog/generation/settings')->assertOk()->assertSee('Réglages de la génération IA');
    get("/admin/blog/generation/{$generation->id}")->assertOk()->assertSee("Génération n° {$generation->id}");
    get('/admin/blog/generation/not-a-number')->assertNotFound();
});

// --- Tableau de bord ------------------------------------------------------------------------------

it('shows the spend of the month against its cap, and whether the generation can run', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 400, 'enabled' => true, 'articles_per_run' => 2]);
    BlogGeneration::factory()->completed(costMicrousd: 1_000_000)->create();
    BlogGeneration::factory()->completed(costMicrousd: 9_000_000)->create(['created_at' => now()->subMonthNoOverflow()]);

    Livewire::test(GenerationDashboard::class)
        ->assertSee('1,00 $')
        ->assertSee('sur 4,00 $ de plafond')
        ->assertSee('Activée')
        ->assertSee('2 articles chaque semaine')
        ->assertSee('Étapes factices')
        ->assertSee('Aucun traitement n\'est à l\'écoute')
        ->assertSee('À l\'arrêt');
});

it('says a worker is listening once one has registered lately', function () {
    app(BlogGenerationWorkerRegistry::class)->register('worker-a');

    Livewire::test(GenerationDashboard::class)
        ->assertSee('À l\'écoute')
        ->assertDontSee('Aucun traitement n\'est à l\'écoute');

    $this->travel(5)->minutes();

    Livewire::test(GenerationDashboard::class)->assertSee('À l\'arrêt');
});

it('tells whether the provider key is there, without ever showing it', function () {
    config(['ai.providers.openrouter.key' => 'sk-or-secret-value-123']);
    actingAs(User::factory()->create());

    Livewire::test(GenerationDashboard::class)
        ->assertSee('Clé du fournisseur d\'IA présente')
        ->assertDontSee('sk-or-secret-value-123');

    expect(get('/admin/blog/generation')->getContent())->not->toContain('sk-or-secret-value-123')
        ->and(get('/admin/blog/generation/settings')->getContent())->not->toContain('sk-or-secret-value-123');

    config(['ai.providers.openrouter.key' => null]);

    Livewire::test(GenerationDashboard::class)->assertSee('Clé du fournisseur d\'IA absente');
});

it('lists the generations in the journal, newest first, with their outcome', function () {
    $old = BlogGeneration::factory()->completed(costMicrousd: 262_500)->create(['topic_title' => 'CE marking for toys']);
    BlogGenerationStep::factory()->for($old, 'generation')->named('topic', 1)->completed()->create(['model' => 'anthropic/claude-sonnet-5.5']);
    BlogGenerationStep::factory()->for($old, 'generation')->named('writing', 2)->completed()->create(['model' => 'openai/gpt-6.1-terra']);
    BlogGeneration::factory()->scheduled()->abandoned(BlogGenerationOutcomeReason::SpendCapReached)->create();
    $failed = BlogGeneration::factory()->failed()->create(['topic_hint' => 'Labels on Etsy']);
    BlogGenerationStep::factory()->for($failed, 'generation')->named('writing', 3)->failed()->create();

    Livewire::test(GenerationDashboard::class)
        ->assertSeeInOrder(['Labels on Etsy', 'Échec à l\'étape « Rédaction »', 'Plafond de dépense du mois atteint', 'CE marking for toys'])
        ->assertDontSee('Simulated provider failure.')
        ->assertSee('Automatique')
        ->assertSee('En échec')
        ->assertSee('0,26 $')
        ->assertSee('anthropic/claude-sonnet-5.5')
        ->assertSee('openai/gpt-6.1-terra')
        ->assertSee('2 400 / 800')
        ->assertSeeHtml('wire:click="retry('.$failed->id.')"')
        ->assertDontSeeHtml('wire:click="retry('.$old->id.')"');
});

it('says so when there is no generation yet', function () {
    Livewire::test(GenerationDashboard::class)->assertSee('Aucune génération pour le moment.');
});

it('queues a generation with the topic and the instructions that were typed', function () {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(GenerationDashboard::class)
        ->set('topicHint', 'Labels on Etsy')
        ->set('instructions', 'Short, for beginners.')
        ->call('launch')
        ->assertHasNoErrors()
        ->assertSet('topicHint', '')
        ->assertSet('instructions', '')
        ->assertDispatched('blog-generation-changed')
        ->assertDispatched('ui-toast', type: 'success');

    $generation = BlogGeneration::query()->sole();

    expect($generation->status)->toBe(BlogGenerationStatus::Queued)
        ->and($generation->topic_hint)->toBe('Labels on Etsy')
        ->and($generation->instructions)->toBe('Short, for beginners.')
        ->and($generation->requested_by)->toBe($user->id);
});

it('refuses a topic or instructions that are too long, in plain words', function () {
    Livewire::test(GenerationDashboard::class)
        ->set('topicHint', str_repeat('a', 301))
        ->set('instructions', str_repeat('a', 2_001))
        ->call('launch')
        ->assertHasErrors(['topicHint', 'instructions'])
        ->assertSee('Le sujet ne doit pas dépasser 300 caractères.');

    expect(BlogGeneration::count())->toBe(0);
});

it('refuses to launch once the monthly spend cap is reached, and says why', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    BlogGeneration::factory()->completed(costMicrousd: 1_000_000)->create();
    Log::spy();

    Livewire::test(GenerationDashboard::class)
        ->assertSee('Le plafond de dépense du mois est atteint')
        ->call('launch')
        ->assertDispatched('ui-toast', type: 'error', title: 'Le plafond de dépense du mois est atteint. Relevez-le dans les réglages pour lancer une génération.')
        ->assertNotDispatched('blog-generation-changed');

    expect(BlogGeneration::count())->toBe(1);
});

it('restarts a failed generation and stops a waiting one from the journal', function () {
    $failed = BlogGeneration::factory()->failed()->create();
    $waiting = BlogGeneration::factory()->create();

    Livewire::test(GenerationDashboard::class)
        ->call('retry', $failed->id)
        ->assertDispatched('ui-toast', type: 'success', title: 'Génération relancée.')
        ->call('cancel', $waiting->id)
        ->assertDispatched('ui-toast', type: 'success', title: 'Génération arrêtée.');

    expect($failed->refresh()->status)->toBe(BlogGenerationStatus::Queued)
        ->and($waiting->refresh()->status)->toBe(BlogGenerationStatus::Cancelled);
});

it('says a running generation will stop after its current step', function () {
    $running = BlogGeneration::factory()->running()->create();
    Log::spy();

    Livewire::test(GenerationDashboard::class)
        ->call('cancel', $running->id)
        ->assertDispatched('ui-toast', type: 'success', title: 'La génération s\'arrêtera après l\'étape en cours.')
        ->call('retry', $running->id)
        ->assertDispatched('ui-toast', type: 'error', title: 'Seule une génération en échec peut être relancée.');

    expect($running->refresh()->status)->toBe(BlogGenerationStatus::Running)
        ->and($running->cancel_requested_at)->not->toBeNull();
});

it('shows a load failure instead of the dashboard when the data cannot be read', function () {
    Log::spy();

    // Panne simulee : la table des generations disparait (la transaction du test la restaure).
    Schema::rename('blog_generations', 'blog_generations_unavailable');

    Livewire::test(GenerationDashboard::class)
        ->assertSee('Le chargement de la génération par IA a échoué.')
        ->assertDontSee('Lancer une génération');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'admin blog generation dashboard'));

    Schema::rename('blog_generations_unavailable', 'blog_generations');
});

it('loads the dashboard in 6 queries, plus 1 for the progress card, whatever the number of generations', function () {
    BlogGeneration::factory()->count(40)->completed()->create()->each(function (BlogGeneration $generation): void {
        BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed()->create();
        BlogGenerationStep::factory()->for($generation, 'generation')->named('writing', 2)->completed()->create();
    });

    DB::enableQueryLog();
    Livewire::test(GenerationDashboard::class);

    // Reglages, depense du mois, worker, nombre de generations, page du journal, cumul des etapes ; plus la
    // carte de suivi (une requete : aucune generation active).
    expect(count(DB::getQueryLog()))->toBe(7);
});

// --- Carte de suivi en direct ---------------------------------------------------------------------

it('shows nothing, and checks rarely, while no generation is active', function () {
    BlogGeneration::factory()->completed()->create();

    DB::enableQueryLog();
    $progress = Livewire::test(GenerationProgress::class);

    expect(count(DB::getQueryLog()))->toBe(1);
    $progress->assertSeeHtml('data-generation-progress="idle"')
        ->assertSeeHtml('wire:poll.15s')
        ->assertDontSeeHtml('wire:poll.2s')
        ->assertDontSee('Génération en cours');
});

it('says at once that a requested generation is starting', function () {
    BlogGeneration::factory()->create(['topic_hint' => 'Labels on Etsy']);

    Livewire::test(GenerationProgress::class)
        ->assertSee('Génération demandée, démarrage…')
        ->assertSee('Labels on Etsy')
        ->assertSeeHtml('wire:poll.2s')
        ->assertSeeHtml('data-generation-progress="queued"');
});

it('follows the running generation step by step, in 2 queries', function () {
    $generation = BlogGeneration::factory()->running()->create(['cost_microusd' => 12_500]);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed(12_500)->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('research', 2)->running()->create();

    DB::enableQueryLog();
    $progress = Livewire::test(GenerationProgress::class);

    expect(count(DB::getQueryLog()))->toBe(2);
    $progress->assertSee('Génération en cours')
        ->assertSee('Étape 2 sur 8')
        ->assertSee('Recherche')
        ->assertSee('0,01 $')
        ->assertSeeInOrder(['Choix du sujet', 'Recherche', 'Rédaction', 'Assemblage du brouillon'])
        ->assertSeeHtml('data-step="research" data-step-status="running"')
        ->assertSeeHtml('data-step="writing" data-step-status="pending"');
});

it('tells the page when the generation it shows changes state', function () {
    $generation = BlogGeneration::factory()->running()->create();

    $progress = Livewire::test(GenerationProgress::class)->assertNotDispatched('blog-generation-progressed');

    $progress->call('refreshProgress')->assertNotDispatched('blog-generation-progressed');

    $generation->update(['status' => BlogGenerationStatus::Completed, 'locked_until' => null, 'finished_at' => now()]);

    $progress->call('refreshProgress')
        ->assertDispatched('blog-generation-progressed')
        ->assertSeeHtml('data-generation-progress="idle"');
});

// --- Detail d'une generation ----------------------------------------------------------------------

it('details a generation step by step, in 2 queries', function () {
    $generation = BlogGeneration::factory()->failed()->create([
        'topic_hint' => 'Labels on Etsy',
        'instructions' => 'Short.',
        'cost_microusd' => 42_500,
        'last_error' => 'Provider is down.',
    ]);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed(30_000)->create(['model' => 'anthropic/claude-sonnet-5.5', 'web_searches' => 2]);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('research', 2)->failed(attempt: 3)->create(['error' => 'Provider is down.', 'cost_microusd' => 12_500, 'cost_is_estimated' => true, 'duration_ms' => 4_000]);

    DB::enableQueryLog();
    $detail = Livewire::test(GenerationDetail::class, ['generation' => $generation->id]);

    expect(count(DB::getQueryLog()))->toBe(2);
    $detail->assertSee('Génération n° '.$generation->id)
        ->assertSee('Labels on Etsy')
        ->assertSee('Short.')
        ->assertSee('Échec à l\'étape « Recherche »')
        ->assertSee('Vous pouvez relancer la génération. Elle reprendra à l\'étape qui a échoué')
        ->assertSee('contactez l\'assistance technique')
        ->assertDontSee('Provider is down.')
        ->assertDontSee('Détail technique')
        ->assertSee('0,04 $')
        ->assertSee('anthropic/claude-sonnet-5.5')
        ->assertSee('1 200 jetons lus, 400 produits')
        ->assertSee('2 recherches web')
        ->assertSee('essai 3')
        ->assertSee('coût estimé')
        ->assertSee('1 étape terminée sur 8')
        ->assertSee('Relancer')
        ->assertDontSeeHtml('wire:poll');
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

it('restarts a failed generation from its detail', function () {
    $generation = BlogGeneration::factory()->failed()->create();

    Livewire::test(GenerationDetail::class, ['generation' => $generation->id])
        ->call('retry')
        ->assertDispatched('ui-toast', type: 'success', title: 'Génération relancée.')
        ->assertSee('En attente')
        ->assertSeeHtml('wire:poll.2s');

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Queued);
});

it('asks a running generation to stop from its detail, and refreshes while it runs', function () {
    $generation = BlogGeneration::factory()->running()->create();

    Livewire::test(GenerationDetail::class, ['generation' => $generation->id])
        ->assertSeeHtml('wire:poll.2s')
        ->assertSee('Arrêter')
        ->call('cancel')
        ->assertDispatched('ui-toast', type: 'success')
        ->assertSee('Arrêt demandé');

    expect($generation->refresh()->cancel_requested_at)->not->toBeNull();
});

it('says a generation cannot be found', function () {
    Livewire::test(GenerationDetail::class, ['generation' => 999_999])
        ->assertSee('Cette génération est introuvable.');
});

// --- Reglages --------------------------------------------------------------------------------------

it('opens the settings on their current values, in a single query', function () {
    BlogAi::saveSettings(['enabled' => true, 'period_count' => 2, 'period_unit' => 'month', 'monthly_spend_cap_cents' => 2_550, 'notification_email' => 'owner@example.com']);

    DB::enableQueryLog();
    $settings = Livewire::test(GenerationSettings::class);

    expect(count(DB::getQueryLog()))->toBe(1);
    $settings->assertSet('enabled', true)
        ->assertSet('periodCount', '2')
        ->assertSet('periodUnit', 'month')
        ->assertSet('monthlySpendCap', '25.50')
        ->assertSet('notificationEmail', 'owner@example.com')
        ->assertSet('models.writing', config('blog.ai.defaults.models.writing'))
        ->assertSee('En français')
        ->assertSeeHtml('<option value="anthropic/claude-sonnet-5.5"></option>');
});

it('saves the settings', function () {
    Livewire::test(GenerationSettings::class)
        ->set('enabled', true)
        ->set('periodCount', '3')
        ->set('periodUnit', 'day')
        ->set('articlesPerRun', '2')
        ->set('pendingDraftsCap', '5')
        ->set('monthlySpendCap', '12.5')
        ->set('priorityTopics', "Etsy\nAmazon")
        ->set('models.writing', 'openai/gpt-6.1-terra')
        ->set('imageMode', 'generated')
        ->set('maxImages', '3')
        ->set('notificationEmail', 'owner@example.com')
        ->set('transparencyNotice.fr', 'Article rédigé avec l\'aide d\'une IA.')
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
        ->and($saved->priorityTopics)->toBe("Etsy\nAmazon")
        ->and($saved->modelFor('writing'))->toBe('openai/gpt-6.1-terra')
        ->and($saved->modelFor('topic'))->toBe(config('blog.ai.defaults.models.topic'))
        ->and($saved->imageMode)->toBe(BlogAiImageMode::Generated)
        ->and($saved->maxImages)->toBe(3)
        ->and($saved->notificationEmail)->toBe('owner@example.com')
        ->and($saved->transparencyNotice['fr'])->toBe('Article rédigé avec l\'aide d\'une IA.');
});

it('refuses settings that make no sense, each with its own message', function () {
    Livewire::test(GenerationSettings::class)
        ->set('periodCount', '0')
        ->set('periodUnit', 'century')
        ->set('articlesPerRun', '11')
        ->set('pendingDraftsCap', '')
        ->set('monthlySpendCap', '-1')
        ->set('models.writing', 'a model with spaces')
        ->set('imageMode', 'hologram')
        ->set('maxImages', '4')
        ->set('notificationEmail', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['periodCount', 'periodUnit', 'articlesPerRun', 'pendingDraftsCap', 'monthlySpendCap', 'models.writing', 'imageMode', 'maxImages', 'notificationEmail'])
        ->assertSee('La période est d\'au moins 1.')
        ->assertSee('Un lot compte 10 articles au plus.')
        ->assertSee('Le plafond de dépense ne peut pas être négatif.')
        ->assertSee('sans espace')
        ->assertSee('Un article a 3 images au plus.')
        ->assertSee('Cette adresse e-mail n\'est pas valide.')
        ->assertNotDispatched('ui-toast');

    expect(app(BlogAiSettingsService::class)->current()->enabled)->toBeFalse();
});
