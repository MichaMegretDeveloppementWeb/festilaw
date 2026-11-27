<?php

use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Generation\QueueBlogGenerationAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Blog\Generation\GenerationSafetyStop;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Models\Setting;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\Simulation\SimulatedGenerationPipeline;
use App\Services\Blog\Generation\UnavailableGenerationPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\Support\BlogAi;

/*
 | Ce qui se regle : les reglages du back-office (une cle JSON de la table settings, completee par les
 | valeurs de depart) et le pipeline choisi par la configuration. Les etapes factices servent aux essais et
 | sont refusees en production.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
});

it('starts from safe defaults: nothing is generated until someone switches it on', function () {
    config(['festilaw.notification_email' => 'team@example.com']);

    $settings = app(BlogAiSettingsService::class)->current();

    expect($settings->enabled)->toBeFalse()
        ->and($settings->periodCount)->toBe(1)
        ->and($settings->periodUnit)->toBe(BlogAiPeriodUnit::Week)
        ->and($settings->articlesPerRun)->toBe(1)
        ->and($settings->pendingDraftsCap)->toBe(3)
        ->and($settings->monthlySpendCapCents)->toBe(1_000)
        ->and($settings->monthlySpendCapMicrousd())->toBe(10_000_000)
        ->and($settings->imageMode)->toBe(BlogAiImageMode::Stock)
        ->and($settings->maxImages)->toBe(1)
        ->and($settings->notificationEmail)->toBe('team@example.com')
        ->and(array_keys($settings->models))->toBe(['topic', 'research', 'writing', 'verification', 'translation', 'image'])
        ->and($settings->modelFor('writing'))->toBe(config('blog.ai.defaults.models.writing'))
        ->and($settings->modelFor('unknown'))->toBeNull();
    // Plus de plafond par article (decision du 18/11) : une generation s'arrete au double du haut annonce, jamais
    // sous 2 centimes (decision du 19/11), pour qu'un texte court puisse etre traduit.
    expect(app(GenerationSafetyStop::class)->ceiling(100_000))->toBe(200_000)
        ->and(app(GenerationSafetyStop::class)->ceiling(2_355))->toBe(20_000);
});

it('saves the settings in a single row and reads them back', function () {
    BlogAi::saveSettings([
        'enabled' => true,
        'period_count' => 2,
        'period_unit' => 'month',
        'articles_per_run' => 3,
        'monthly_spend_cap_cents' => 2_500,
        'models' => ['writing' => 'openai/gpt-6.1-terra'],
        'image_mode' => 'none',
        'max_images' => 3,
        'notification_email' => 'owner@example.com',
    ]);

    $settings = app(BlogAiSettingsService::class)->current();

    expect(Setting::query()->where('key', 'blog.ai')->count())->toBe(1)
        ->and($settings->enabled)->toBeTrue()
        ->and($settings->periodCount)->toBe(2)
        ->and($settings->periodUnit)->toBe(BlogAiPeriodUnit::Month)
        ->and($settings->articlesPerRun)->toBe(3)
        ->and($settings->monthlySpendCapCents)->toBe(2_500)
        ->and($settings->modelFor('writing'))->toBe('openai/gpt-6.1-terra')
        ->and($settings->modelFor('topic'))->toBe(config('blog.ai.defaults.models.topic'))
        ->and($settings->imageMode)->toBe(BlogAiImageMode::None)
        ->and($settings->maxImages)->toBe(3)
        ->and($settings->notificationEmail)->toBe('owner@example.com');
});

it('falls back on the defaults when the stored settings are unreadable', function () {
    Setting::query()->create(['key' => 'blog.ai', 'value' => '{not json']);

    expect(app(BlogAiSettingsService::class)->current()->enabled)->toBeFalse();
});

it('uses the simulated steps outside production', function () {
    expect(app(GenerationPipeline::class))->toBeInstanceOf(SimulatedGenerationPipeline::class);
});

it('refuses the simulated steps in production', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    expect(app(GenerationPipeline::class))->toBeInstanceOf(UnavailableGenerationPipeline::class)
        ->and(fn () => app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData))
        ->toThrow(function (BlogGenerationException $e): void {
            expect($e->getMessage())->toContain('refused in production');
        });

    expect(BlogGeneration::count())->toBe(0);
});

it('refuses a pipeline name it does not know', function () {
    config(['blog.ai.pipeline' => 'unknown-pipeline']);

    expect(fn () => app(GenerationPipeline::class)->steps(BlogGenerationType::Article))
        ->toThrow(function (BlogGenerationException $e): void {
            expect($e->getMessage())->toContain('[unknown-pipeline]');
        });
});

it('runs the editorial pipeline by default, in fifteen steps, production included', function () {
    config(['blog.ai.pipeline' => 'editorial']);
    app()->detectEnvironment(fn (): string => 'production');

    expect(array_map(fn ($step): string => $step->key(), app(GenerationPipeline::class)->steps(BlogGenerationType::Article)))
        ->toBe(['topic_scan', 'topic', 'research_notes', 'research', 'writing', 'fact_search', 'verification', 'style', 'category', 'translation_fr', 'version_check_fr', 'translation_es', 'version_check_es', 'images', 'assembly']);
});

it('runs a whole simulated generation: ten steps, a made-up cost, no call to any model', function () {
    $generation = BlogGeneration::factory()->create();

    app(ProcessNextBlogGenerationAction::class)->execute('worker-a');

    $generation->refresh();
    $costPerStep = config('blog.ai.simulation.cost_microusd_per_step');

    expect($generation->status)->toBe(BlogGenerationStatus::Completed)
        ->and($generation->steps->pluck('step')->all())->toBe(['topic', 'research', 'writing', 'verification', 'style', 'category', 'translation_fr', 'translation_es', 'images', 'assembly'])
        ->and($generation->cost_microusd)->toBe(9 * $costPerStep)
        ->and($generation->steps->firstWhere('step', 'writing')->model)->toBe(config('blog.ai.defaults.models.writing'))
        ->and($generation->steps->firstWhere('step', 'images')->model)->toBe(config('blog.ai.defaults.models.image'))
        ->and($generation->steps->firstWhere('step', 'writing')->cost_is_estimated)->toBeTrue()
        ->and($generation->steps->firstWhere('step', 'assembly')->cost_microusd)->toBe(0)
        ->and($generation->blog_post_id)->toBeNull();
    Sleep::assertSleptTimes(10);
});

it('runs the single step of a simulated assisted translation, which hands back the text marked with its language', function () {
    $generation = BlogGeneration::factory()->create(['type' => BlogGenerationType::Translation, 'payload' => ['translation' => ['from' => 'en', 'to' => 'fr', 'article' => ['title' => 'Labels', 'body' => 'Text.']]]]);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-a');

    $step = $generation->refresh()->steps->sole();
    expect($step->step)->toBe('translation')
        ->and($step->output['article']['title'])->toBe('[FR] Labels');
});

it('can make a simulated step fail, or find no topic, to try those paths by hand', function () {
    config(['blog.ai.simulation.fail_step' => 'research']);
    $failing = BlogGeneration::factory()->create();
    app(ProcessNextBlogGenerationAction::class)->execute('worker-a');

    config(['blog.ai.simulation.fail_step' => null, 'blog.ai.simulation.stop_without_topic' => true]);
    $withoutTopic = BlogGeneration::factory()->create();
    app(ProcessNextBlogGenerationAction::class)->execute('worker-a');

    expect($failing->refresh()->status)->toBe(BlogGenerationStatus::Failed)
        ->and($failing->steps->firstWhere('step', 'research')->attempt)->toBe(3)
        ->and($withoutTopic->refresh()->status)->toBe(BlogGenerationStatus::Abandoned)
        ->and($withoutTopic->outcome_reason)->toBe(BlogGenerationOutcomeReason::NoRelevantTopic);
});

it('reads settings saved with the topics and the transparency notice, then drops them at the next save', function () {
    // Retires le 19/11 (decision de Micha) : un sujet se donne au lancement, et la mention n'est pas obligatoire.
    Setting::query()->updateOrCreate(
        ['key' => BlogAiSettingsService::KEY],
        ['value' => json_encode(['enabled' => true, 'priority_topics' => ['Etsy'], 'avoided_topics' => 'Crypto', 'transparency_notice' => ['fr' => 'Rédigé avec une IA.']])],
    );

    $settings = app(BlogAiSettingsService::class)->current();
    BlogAi::saveSettings($settings->toStored());

    $stored = json_decode((string) Setting::query()->where('key', BlogAiSettingsService::KEY)->value('value'), true);

    expect($settings->enabled)->toBeTrue()
        ->and($stored)->not->toHaveKeys(['priority_topics', 'avoided_topics', 'transparency_notice'])
        ->and($stored['enabled'])->toBeTrue();
});
