<?php

use App\Actions\Blog\Generation\PlanScheduledBlogGenerationsAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationEffort;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\ScheduledGenerationPlan;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Exceptions\Blog\BlogGenerationException;
use App\Mail\BlogGenerationPaused;
use App\Models\BlogGeneration;
use App\Models\BlogPost;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use App\Services\Blog\Generation\BlogGenerationPlannerHeartbeat;
use App\Services\Blog\Generation\UnavailableGenerationPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BlogAi;
use Tests\Support\FakeGenerationPipeline;

/*
 | Generation automatique : a chaque creneau du calendrier (jour et heure choisis, a l'heure de Paris, decision de
 | Micha du 26/11), la tache planifiee met en file un lot d'articles. Elle ne genere rien elle-meme. Un lot du mais
 | bloque par un plafond est inscrit au journal, sans repeter deux fois de suite la meme raison.
 |
 | L'horloge part du lundi 16/11/2026 a 06:00:05 UTC, soit 07:00:05 a Paris (heure d'hiver).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Http::preventStrayRequests();
    FakeGenerationPipeline::bind();
    $this->travelTo('2026-11-16 06:00:05');
});

function planScheduledGenerations(bool $dryRun = false): ScheduledGenerationPlan
{
    return app(PlanScheduledBlogGenerationsAction::class)->execute($dryRun);
}

/**
 * Enregistre une generation automatique activee le lundi a 7 h (heure de Paris), par l'ecran des reglages.
 *
 * @param  array<string, mixed>  $overrides
 */
function saveAutomatic(array $overrides = []): BlogAiSettings
{
    return BlogAi::saveSettings(['enabled' => true, 'weekday' => 1, 'month_day' => 16, 'hour' => 7, ...$overrides]);
}

/**
 * La meme, enregistree la semaine d'avant : le creneau du lundi 16/11 a 7 h attend son lot.
 *
 * @param  array<string, mixed>  $overrides
 */
function enableAutomatic(array $overrides = []): BlogAiSettings
{
    $settings = saveAutomatic($overrides);
    BlogAi::startCalendarAt('2026-11-09 06:00:00');

    return $settings;
}

it('does nothing while the automatic generation is switched off', function () {
    $plan = planScheduledGenerations();

    expect($plan->enabled)->toBeFalse()
        ->and($plan->launched)->toBe(0)
        ->and(BlogGeneration::count())->toBe(0);
});

it('waits for the first chosen day and hour after being switched on, instead of queueing at once', function () {
    BlogAi::saveSettings(['enabled' => true, 'weekday' => 2, 'hour' => 9, 'articles_per_run' => 2]);

    $notDue = planScheduledGenerations();

    // Mardi 17/11 a 9 h a Paris, soit 8 h UTC.
    expect($notDue->launched)->toBe(0)
        ->and($notDue->nextLaunchAt->toDateTimeString())->toBe('2026-11-17 08:00:00');

    $this->travelTo('2026-11-17 07:59:59');
    expect(planScheduledGenerations()->launched)->toBe(0);

    $this->travelTo('2026-11-17 08:00:05');
    $plan = planScheduledGenerations();
    $generations = BlogGeneration::query()->orderBy('id')->get();

    expect($plan->launched)->toBe(2)
        ->and($generations)->toHaveCount(2)
        ->and($generations->pluck('batch_uuid')->unique())->toHaveCount(1)
        ->and($generations->every(fn (BlogGeneration $generation): bool => $generation->trigger === BlogGenerationTrigger::Scheduled
            && $generation->status === BlogGenerationStatus::Queued
            && $generation->batch_notified_at === null))->toBeTrue();
});

it('queues one batch per slot, then waits for the next one', function () {
    enableAutomatic();

    expect(planScheduledGenerations()->launched)->toBe(1)
        ->and(planScheduledGenerations()->launched)->toBe(0);

    $this->travelTo('2026-11-23 05:59:59');
    $notDue = planScheduledGenerations();

    expect($notDue->launched)->toBe(0)
        ->and($notDue->nextLaunchAt->toDateTimeString())->toBe('2026-11-23 06:00:00');

    $this->travelTo('2026-11-23 06:00:05');

    expect(planScheduledGenerations()->launched)->toBe(1)
        ->and(BlogGeneration::count())->toBe(2);
});

it('counts the slots every few days, weeks or months', function (array $rhythm, string $tooEarly, string $due) {
    enableAutomatic($rhythm);
    expect(planScheduledGenerations()->launched)->toBe(1);

    $this->travelTo($tooEarly);
    expect(planScheduledGenerations()->launched)->toBe(0);

    $this->travelTo($due);
    expect(planScheduledGenerations()->launched)->toBe(1);
})->with([
    'every 3 days' => [['period_count' => 3, 'period_unit' => 'day'], '2026-11-19 05:59:00', '2026-11-19 06:00:05'],
    'every 2 weeks, on Monday' => [['period_count' => 2, 'period_unit' => 'week'], '2026-11-23 06:00:05', '2026-11-30 06:00:05'],
    'every month, on the 16th' => [['period_count' => 1, 'period_unit' => 'month'], '2026-12-16 05:59:00', '2026-12-16 06:00:05'],
]);

it('gives a single batch for several missed slots', function () {
    enableAutomatic();

    $this->travelTo('2026-11-30 06:00:05');

    expect(planScheduledGenerations()->launched)->toBe(1)
        ->and(planScheduledGenerations()->launched)->toBe(0)
        ->and(BlogGeneration::count())->toBe(1);
});

it('lets a held batch leave when the pause ends, the next one keeping its date', function () {
    enableAutomatic(['pending_drafts_cap' => 1]);
    BlogPost::factory()->fromAi()->create();

    expect(planScheduledGenerations()->blockedBy)->toBe(BlogGenerationOutcomeReason::PendingDraftsCapReached);

    // Mercredi, le plafond est releve : le lot attendu part aussitot.
    $this->travelTo('2026-11-18 10:00:05');
    saveAutomatic(['pending_drafts_cap' => 2]);
    expect(planScheduledGenerations()->launched)->toBe(1);

    // Le lot suivant reste au lundi 23/11 a 7 h.
    $this->travelTo('2026-11-23 06:00:05');
    expect(planScheduledGenerations()->launched)->toBe(1);
});

it('starts the calendar again when the day or the hour changes, not when the number of articles does', function () {
    enableAutomatic();
    planScheduledGenerations();
    $startsAt = fn () => app(BlogAiSettingsService::class)->current()->startsAt?->toDateTimeString();

    saveAutomatic(['articles_per_run' => 2]);
    expect($startsAt())->toBe('2026-11-09 06:00:00');

    saveAutomatic(['weekday' => 3]);
    $notDue = planScheduledGenerations();

    // Mercredi 18/11 a 7 h a Paris.
    expect($startsAt())->toBe('2026-11-16 06:00:05')
        ->and($notDue->launched)->toBe(0)
        ->and($notDue->nextLaunchAt->toDateTimeString())->toBe('2026-11-18 06:00:00');
});

it('still queues the batch above the monthly spend cap, which only warns', function () {
    // Decision de Micha du 17/11 : le plafond du mois ne retient rien. L'avertissement part a la fin d'une generation.
    enableAutomatic(['monthly_spend_cap_cents' => 100, 'notification_email' => 'owner@example.com']);
    BlogGeneration::factory()->completed(costMicrousd: 1_000_000)->create();

    $plan = planScheduledGenerations();

    expect($plan->launched)->toBe(1)
        ->and($plan->blockedBy)->toBeNull()
        ->and(BlogGeneration::query()->where('status', BlogGenerationStatus::Abandoned)->exists())->toBeFalse();
    Mail::assertNothingSent();
});

it('queues the batch whatever the estimated cost, each generation keeping the announced cost', function () {
    // Decision de Micha du 18/11 : plus de plafond par article. Sonnet a la redaction et trois images generees partent aussi.
    $settings = enableAutomatic([
        'articles_per_run' => 2, 'image_mode' => 'generated', 'max_images' => 3,
        'models' => ['writing' => 'anthropic/claude-sonnet-5.5'],
    ]);

    $plan = planScheduledGenerations();

    $announced = app(BlogGenerationCostForecaster::class)->forSettings($settings);
    expect($plan->launched)->toBe(2)
        ->and($plan->blockedBy)->toBeNull()
        ->and(BlogGeneration::query()->where('trigger', BlogGenerationTrigger::Scheduled)->pluck('forecast_high_microusd')->all())
        ->toBe([$announced->highMicrousd, $announced->highMicrousd]);
    Mail::assertNothingSent();
});

it('writes a later holding again in the journal', function () {
    enableAutomatic(['pending_drafts_cap' => 1]);
    BlogPost::factory()->fromAi()->create();
    planScheduledGenerations();

    // Le plafond est releve : le lot attendu part.
    saveAutomatic(['pending_drafts_cap' => 2]);
    expect(planScheduledGenerations()->launched)->toBe(1);

    // Au creneau suivant, le plafond retient de nouveau : c'est une nouvelle ligne du journal.
    saveAutomatic(['pending_drafts_cap' => 1]);
    $this->travelTo('2026-11-23 06:00:05');
    planScheduledGenerations();

    expect(BlogGeneration::query()->where('status', BlogGenerationStatus::Abandoned)->count())->toBe(2);
});

it('only queues what the pending drafts cap leaves room for', function () {
    enableAutomatic(['articles_per_run' => 3, 'pending_drafts_cap' => 3]);
    BlogPost::factory()->fromAi()->count(2)->create();
    // Ne comptent pas : un brouillon saisi a la main, un article genere deja publie, un article genere puis
    // depublie (controle du 20/11 : depublier de vieux articles ne doit pas arreter la generation).
    BlogPost::factory()->create();
    BlogPost::factory()->fromAi()->published()->create();
    BlogPost::factory()->fromAi()->create(['published_at' => now()->subMonth()]);

    expect(planScheduledGenerations()->launched)->toBe(1);
});

it('does not queue anything while too many generated drafts wait for a review, and says so once', function () {
    enableAutomatic(['pending_drafts_cap' => 2, 'notification_email' => 'owner@example.com']);
    BlogPost::factory()->fromAi()->count(2)->create();

    $plan = planScheduledGenerations();
    planScheduledGenerations();

    expect($plan->blockedBy)->toBe(BlogGenerationOutcomeReason::PendingDraftsCapReached)
        ->and(BlogGeneration::query()->sole()->outcome_reason)->toBe(BlogGenerationOutcomeReason::PendingDraftsCapReached);
    Mail::assertSent(BlogGenerationPaused::class, 1);
    Mail::assertSent(BlogGenerationPaused::class, fn (BlogGenerationPaused $mail): bool => $mail->hasTo('owner@example.com')
        && $mail->pendingDrafts === 2
        && $mail->draftsUrl === route('admin.blog.posts.index', ['origin' => 'ai', 'status' => 'draft']));
});

it('writes a pause message that says how the generation starts again', function () {
    $html = (new BlogGenerationPaused(3, 'https://example.test/admin/blog/posts?origin=ai&status=draft'))->render();

    expect($html)->toContain('3 brouillons générés attendent votre relecture')
        ->toContain('Dès qu\'un brouillon est publié ou supprimé, la génération reprend d\'elle-même')
        ->toContain('href="https://example.test/admin/blog/posts?origin=ai&amp;status=draft"');
});

it('tells what it would do, without writing or sending anything, on a dry run', function () {
    enableAutomatic(['articles_per_run' => 2]);
    // La migration qui a pose la surveillance a deja ecrit une premiere trace : l'essai a blanc n'y touche pas.
    $heartbeat = app(BlogGenerationPlannerHeartbeat::class)->lastRunAt();

    $this->artisan('festilaw:blog-generation-schedule', ['--dry' => true])
        ->expectsOutputToContain('[DRY-RUN] Lot planifie : 2 generation(s)')
        ->assertSuccessful();

    enableAutomatic(['pending_drafts_cap' => 1]);
    BlogPost::factory()->fromAi()->create();

    $this->artisan('festilaw:blog-generation-schedule', ['--dry' => true])
        ->expectsOutputToContain('[DRY-RUN] Lot du mais non lance : pending_drafts_cap_reached')
        ->assertSuccessful();

    BlogPost::query()->delete();
    $this->travelTo('2026-11-17 10:00:00');
    BlogAi::startCalendarAt('2026-11-16 08:00:00');

    $this->artisan('festilaw:blog-generation-schedule', ['--dry' => true])
        ->expectsOutputToContain('[DRY-RUN] Aucun lot du avant le 23/11/2026 a 07:00 (heure de Paris).')
        ->assertSuccessful();

    expect(BlogGeneration::count())->toBe(0)
        ->and(app(BlogGenerationPlannerHeartbeat::class)->lastRunAt()?->toIso8601String())->toBe($heartbeat?->toIso8601String());
    Mail::assertNothingSent();
});

it('queues the batch from the scheduled command, stays silent when nothing is due, and records every pass', function () {
    enableAutomatic();

    $this->artisan('festilaw:blog-generation-schedule')
        ->expectsOutputToContain('Lot planifie : 1 generation(s)')
        ->assertSuccessful();

    $this->travelTo('2026-11-16 07:00:00');

    $this->artisan('festilaw:blog-generation-schedule')
        ->doesntExpectOutputToContain('Lot')
        ->assertSuccessful();

    expect(BlogGeneration::count())->toBe(1)
        ->and(app(BlogGenerationPlannerHeartbeat::class)->lastRunAt()?->toDateTimeString())->toBe('2026-11-16 07:00:00');
});

it('records the pass even while the automatic generation is switched off', function () {
    planScheduledGenerations();

    expect(app(BlogGenerationPlannerHeartbeat::class)->lastRunAt()?->toDateTimeString())->toBe('2026-11-16 06:00:05');
});

it('fails loudly, without queueing, when the pipeline cannot be used', function () {
    enableAutomatic();
    $this->app->instance(GenerationPipeline::class, new UnavailableGenerationPipeline(BlogGenerationException::simulatedPipelineInProduction()));

    $this->artisan('festilaw:blog-generation-schedule')->assertFailed();

    expect(BlogGeneration::count())->toBe(0);
});

it('gives every generation of a batch the default images and effort of the settings, fixed at launch', function () {
    enableAutomatic(['articles_per_run' => 2, 'image_mode' => 'none', 'max_images' => 2, 'effort' => 'high']);

    planScheduledGenerations();

    // Les reglages changent apres le lancement : le lot garde les images qu'il avait.
    saveAutomatic(['image_mode' => 'generated', 'max_images' => 3, 'effort' => 'low']);

    $generations = BlogGeneration::query()->get();
    $images = $generations->map(fn (BlogGeneration $generation): ?GenerationImageOptions => GenerationImageOptions::fromPayload($generation->payload));

    expect($images)->toHaveCount(2)
        ->and($images->every(fn (?GenerationImageOptions $options): bool => $options?->mode === BlogAiImageMode::None && $options->maxImages === 2))->toBeTrue()
        ->and($generations->every(fn (BlogGeneration $generation): bool => GenerationEffort::fromPayload($generation->payload) === BlogEffortLevel::High))->toBeTrue();
});
