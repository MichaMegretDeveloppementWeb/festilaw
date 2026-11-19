<?php

use App\Actions\Blog\Generation\PlanScheduledBlogGenerationsAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Blog\Generation\GenerationEffort;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\ScheduledGenerationPlan;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Models\BlogPost;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use App\Services\Blog\Generation\UnavailableGenerationPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BlogAi;
use Tests\Support\FakeGenerationPipeline;

/*
 | Generation automatique : une fois par periode reglee au back-office, la tache planifiee met en file un
 | lot d'articles. Elle ne genere rien elle-meme. Un lot du mais bloque par un plafond est inscrit au
 | journal, sans repeter deux fois de suite la meme raison.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Http::preventStrayRequests();
    FakeGenerationPipeline::bind();
    $this->travelTo('2026-11-16 06:00:05'); // un lundi, a l'heure du controle quotidien
});

function planScheduledGenerations(bool $dryRun = false): ScheduledGenerationPlan
{
    return app(PlanScheduledBlogGenerationsAction::class)->execute($dryRun);
}

it('does nothing while the automatic generation is switched off', function () {
    $plan = planScheduledGenerations();

    expect($plan->enabled)->toBeFalse()
        ->and($plan->launched)->toBe(0)
        ->and(BlogGeneration::count())->toBe(0);
});

it('queues the first batch as soon as it is switched on', function () {
    BlogAi::saveSettings(['enabled' => true, 'articles_per_run' => 2]);

    $plan = planScheduledGenerations();

    $generations = BlogGeneration::query()->orderBy('id')->get();

    expect($plan->launched)->toBe(2)
        ->and($generations)->toHaveCount(2)
        ->and($generations->pluck('batch_uuid')->unique())->toHaveCount(1)
        ->and($generations->every(fn (BlogGeneration $generation): bool => $generation->trigger === BlogGenerationTrigger::Scheduled
            && $generation->status === BlogGenerationStatus::Queued
            && $generation->batch_notified_at === null))->toBeTrue();
});

it('waits for the period to elapse before queueing the next batch', function () {
    BlogAi::saveSettings(['enabled' => true, 'period_count' => 1, 'period_unit' => 'week']);
    planScheduledGenerations();

    // Relancee le meme jour, puis six jours plus tard : rien n'est du.
    expect(planScheduledGenerations()->launched)->toBe(0);

    $this->travelTo('2026-11-22 06:00:00');
    $notDue = planScheduledGenerations();

    expect($notDue->launched)->toBe(0)
        ->and($notDue->dueAt->toDateString())->toBe('2026-11-23')
        ->and(BlogGeneration::count())->toBe(1);

    // Le lundi suivant, meme quelques secondes plus tot que la semaine d'avant : le lot est du (au jour pres).
    $this->travelTo('2026-11-23 06:00:00');

    expect(planScheduledGenerations()->launched)->toBe(1)
        ->and(BlogGeneration::count())->toBe(2);
});

it('counts the period in days or months as well', function (int $count, string $unit, string $tooEarly, string $due) {
    BlogAi::saveSettings(['enabled' => true, 'period_count' => $count, 'period_unit' => $unit]);
    planScheduledGenerations();

    $this->travelTo($tooEarly);
    expect(planScheduledGenerations()->launched)->toBe(0);

    $this->travelTo($due);
    expect(planScheduledGenerations()->launched)->toBe(1);
})->with([
    'every 3 days' => [3, 'day', '2026-11-18 06:00:00', '2026-11-19 06:00:00'],
    'every month' => [1, 'month', '2026-12-15 06:00:00', '2026-12-16 06:00:00'],
]);

it('still queues the batch above the monthly spend cap, which only warns', function () {
    // Decision de Micha du 17/11 : le plafond du mois ne retient rien. L'avertissement part a la fin d'une generation.
    BlogAi::saveSettings(['enabled' => true, 'monthly_spend_cap_cents' => 100, 'notification_email' => 'owner@example.com']);
    BlogGeneration::factory()->completed(costMicrousd: 1_000_000)->create();

    $plan = planScheduledGenerations();

    expect($plan->launched)->toBe(1)
        ->and($plan->blockedBy)->toBeNull()
        ->and(BlogGeneration::query()->where('status', BlogGenerationStatus::Abandoned)->exists())->toBeFalse();
    Mail::assertNothingSent();
});

it('queues the batch whatever the estimated cost, each generation keeping the announced cost', function () {
    // Decision de Micha du 18/11 : plus de plafond par article. Sonnet a la redaction et trois images generees partent aussi.
    $settings = BlogAi::saveSettings([
        'enabled' => true, 'articles_per_run' => 2, 'image_mode' => 'generated', 'max_images' => 3,
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

it('queues the batch as soon as the holding reason falls, and writes a later holding again', function () {
    BlogAi::saveSettings(['enabled' => true, 'pending_drafts_cap' => 1]);
    BlogPost::factory()->fromAi()->create();
    planScheduledGenerations();

    // Le plafond de brouillons est releve : le lot attendu part.
    BlogAi::saveSettings(['enabled' => true, 'pending_drafts_cap' => 2]);
    expect(planScheduledGenerations()->launched)->toBe(1);

    // Une periode plus tard, le plafond retient de nouveau : c'est une nouvelle ligne du journal.
    BlogAi::saveSettings(['enabled' => true, 'pending_drafts_cap' => 1]);
    $this->travelTo('2026-11-23 06:00:05');
    planScheduledGenerations();

    expect(BlogGeneration::query()->where('status', BlogGenerationStatus::Abandoned)->count())->toBe(2);
});

it('only queues what the pending drafts cap leaves room for', function () {
    BlogAi::saveSettings(['enabled' => true, 'articles_per_run' => 3, 'pending_drafts_cap' => 3]);
    BlogPost::factory()->fromAi()->count(2)->create();
    // Ne comptent pas : un brouillon saisi a la main, un article genere deja publie.
    BlogPost::factory()->create();
    BlogPost::factory()->fromAi()->published()->create();

    expect(planScheduledGenerations()->launched)->toBe(1);
});

it('does not queue anything while too many generated drafts wait for a review', function () {
    BlogAi::saveSettings(['enabled' => true, 'pending_drafts_cap' => 2]);
    BlogPost::factory()->fromAi()->count(2)->create();

    $plan = planScheduledGenerations();

    expect($plan->blockedBy)->toBe(BlogGenerationOutcomeReason::PendingDraftsCapReached)
        ->and(BlogGeneration::query()->sole()->outcome_reason)->toBe(BlogGenerationOutcomeReason::PendingDraftsCapReached);
    Mail::assertNothingSent();
});

it('tells what it would do, without writing or sending anything, on a dry run', function () {
    BlogAi::saveSettings(['enabled' => true, 'articles_per_run' => 2]);

    $this->artisan('festilaw:blog-generation-schedule', ['--dry' => true])
        ->expectsOutputToContain('[DRY-RUN] Lot planifie : 2 generation(s)')
        ->assertSuccessful();

    BlogAi::saveSettings(['enabled' => true, 'pending_drafts_cap' => 1]);
    BlogPost::factory()->fromAi()->create();

    $this->artisan('festilaw:blog-generation-schedule', ['--dry' => true])
        ->expectsOutputToContain('[DRY-RUN] Lot du mais non lance : pending_drafts_cap_reached')
        ->assertSuccessful();

    expect(BlogGeneration::count())->toBe(0);
    Mail::assertNothingSent();
});

it('queues the batch from the scheduled command and stays silent on a day with nothing to do', function () {
    BlogAi::saveSettings(['enabled' => true]);

    $this->artisan('festilaw:blog-generation-schedule')
        ->expectsOutputToContain('Lot planifie : 1 generation(s)')
        ->assertSuccessful();

    $this->artisan('festilaw:blog-generation-schedule')
        ->doesntExpectOutputToContain('Lot')
        ->assertSuccessful();

    expect(BlogGeneration::count())->toBe(1);
});

it('fails loudly, without queueing, when the pipeline cannot be used', function () {
    BlogAi::saveSettings(['enabled' => true]);
    $this->app->instance(GenerationPipeline::class, new UnavailableGenerationPipeline(BlogGenerationException::simulatedPipelineInProduction()));

    $this->artisan('festilaw:blog-generation-schedule')->assertFailed();

    expect(BlogGeneration::count())->toBe(0);
});

it('gives every generation of a batch the default images and effort of the settings, fixed at launch', function () {
    BlogAi::saveSettings(['enabled' => true, 'articles_per_run' => 2, 'image_mode' => 'none', 'max_images' => 2, 'effort' => 'high']);

    planScheduledGenerations();

    // Les reglages changent apres le lancement : le lot garde les images qu'il avait.
    BlogAi::saveSettings(['enabled' => true, 'image_mode' => 'generated', 'max_images' => 3, 'effort' => 'low']);

    $generations = BlogGeneration::query()->get();
    $images = $generations->map(fn (BlogGeneration $generation): ?GenerationImageOptions => GenerationImageOptions::fromPayload($generation->payload));

    expect($images)->toHaveCount(2)
        ->and($images->every(fn (?GenerationImageOptions $options): bool => $options?->mode === BlogAiImageMode::None && $options->maxImages === 2))->toBeTrue()
        ->and($generations->every(fn (BlogGeneration $generation): bool => GenerationEffort::fromPayload($generation->payload) === BlogEffortLevel::High))->toBeTrue();
});
