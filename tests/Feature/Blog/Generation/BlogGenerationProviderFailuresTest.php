<?php

declare(strict_types=1);

use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Generation\QueueBlogGenerationAction;
use App\Actions\Blog\Generation\RetryBlogGenerationAction;
use App\Actions\Blog\Generation\RunBlogGenerationAction;
use App\Ai\Agents\Blog\TopicAgent;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Enums\Blog\Generation\BlogAiFailureKind;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Models\BlogImage;
use App\Repositories\Blog\PendingBlogGenerationRepository;
use App\Services\Blog\Generation\BlogGenerationLabels;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\Support\BlogAi;
use Tests\Support\FakeGenerationPipeline;

/*
 | Pannes des fournisseurs (controle du 20/11) : chaque panne a sa sorte, qui decide de ce que fait le moteur.
 | Une cle refusee, un modele retire, un refus ou une reponse coupee ne se reessaient pas ; une panne passagere
 | attend, sans que le worker dorme, puis une generation automatique est relancee une fois une heure plus tard.
 | Les modeles sont figes au lancement, et un arret a la main est vu avant chaque appel.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
    $this->travelTo('2026-11-21 06:00:00');
});

function providerWorkerPass(): bool
{
    return app(ProcessNextBlogGenerationAction::class)->execute('worker-a');
}

function transientFailure(): BlogAiProviderException
{
    return BlogAiProviderException::callFailed('writing', new RuntimeException('HTTP 503 from the provider.'), kind: BlogAiFailureKind::Transient);
}

// --- Le classement des pannes d'OpenRouter

it('names each refusal of OpenRouter', function (int $status, string $message, BlogAiFailureKind $kind) {
    config(['ai.providers.openrouter.key' => 'test-key']);
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['code' => $status, 'message' => $message]], $status)]);

    expect(fn () => app(BlogAgentCaller::class)->call('topic', new TopicAgent('Choose.'), 'Topic.', 'openai/gpt-6-luna', 90))
        ->toThrow(fn (BlogAiProviderException $e) => expect($e->kind)->toBe($kind));
})->with([
    'key refused' => [401, 'No auth credentials found', BlogAiFailureKind::KeyRejected],
    'unknown model' => [400, 'openai/gpt-6-luna is not a valid model ID', BlogAiFailureKind::ModelUnavailable],
    'no provider left' => [404, 'No endpoints found that support the requested parameters', BlogAiFailureKind::ModelUnavailable],
    'key limit' => [403, 'Key limit exceeded', BlogAiFailureKind::Credit],
    'moderation' => [403, 'Input was flagged by moderation', BlogAiFailureKind::Refused],
    'rate limit' => [429, 'Rate limit exceeded', BlogAiFailureKind::Transient],
    'server error' => [500, 'Internal server error', BlogAiFailureKind::Transient],
    'overloaded' => [503, 'Service unavailable', BlogAiFailureKind::Transient],
    'bad request' => [400, 'Invalid value for temperature', BlogAiFailureKind::Other],
]);

it('takes a cut connection or an error inside a 200 answer for a passing outage', function (Closure $answer) {
    config(['ai.providers.openrouter.key' => 'test-key']);
    Http::fake(['openrouter.ai/*' => $answer()]);

    expect(fn () => app(BlogAgentCaller::class)->call('topic', new TopicAgent('Choose.'), 'Topic.', 'openai/gpt-6-luna', 90))
        ->toThrow(fn (BlogAiProviderException $e) => expect($e->kind)->toBe(BlogAiFailureKind::Transient));
})->with([
    'connection cut' => [fn () => Http::failedConnection()],
    'error in a 200 answer' => [fn () => Http::response(['error' => ['code' => 502, 'message' => 'Upstream error']], 200)],
]);

it('sends no call once the generation was stopped by hand', function () {
    $generation = BlogGeneration::factory()->running()->create(['cancel_requested_at' => now()]);

    expect(fn () => app(BlogAgentCaller::class)->call('writing', new TopicAgent('Choose.'), 'Topic.', 'openai/gpt-6-luna', 90, generationId: $generation->id))
        ->toThrow(fn (BlogAiProviderException $e) => expect($e->kind)->toBe(BlogAiFailureKind::Cancelled));
    Http::assertNothingSent();
});

// --- Ce que fait le moteur

it('stops at once, without retrying, when the provider refuses the key or no longer offers the model', function (Closure $failure, BlogGenerationOutcomeReason $reason, string $label) {
    $pipeline = FakeGenerationPipeline::bind()->on('writing', fn () => throw $failure());
    $generation = BlogGeneration::factory()->create();

    providerWorkerPass();

    $writing = $generation->refresh()->steps->firstWhere('step', 'writing');
    expect($generation->status)->toBe(BlogGenerationStatus::Failed)
        ->and($generation->outcome_reason)->toBe($reason)
        ->and($writing->attempt)->toBe(1)
        ->and($pipeline->calls('writing'))->toBe(1)
        ->and(app(BlogGenerationLabels::class)->outcome($reason, 'writing'))->toBe($label);
    Sleep::assertNeverSlept();
})->with([
    'key refused' => [fn () => BlogAiProviderException::keyRejected('writing', new RuntimeException('HTTP 401')), BlogGenerationOutcomeReason::AiKeyRejected, 'Clé du fournisseur d\'IA refusée'],
    'model retired' => [fn () => BlogAiProviderException::modelUnavailable('writing', 'openai/gpt-6-luna', new RuntimeException('HTTP 404')), BlogGenerationOutcomeReason::AiModelUnavailable, 'Modèle retiré par le fournisseur d\'IA, à l\'étape « Rédaction »'],
]);

it('lets a generation wait two then ten minutes after a passing outage, without holding the worker, then fails a manual one', function () {
    $pipeline = FakeGenerationPipeline::bind()->on('writing', fn () => throw transientFailure());
    $generation = BlogGeneration::factory()->create();

    providerWorkerPass();

    $generation->refresh();
    expect($generation->status)->toBe(BlogGenerationStatus::Running)
        ->and($generation->lock_owner)->toBeNull()
        ->and($generation->not_before->toDateTimeString())->toBe('2026-11-21 06:02:00')
        ->and(app(PendingBlogGenerationRepository::class)->nextId(CarbonImmutable::now()))->toBeNull();

    $this->travelTo('2026-11-21 06:02:00');
    providerWorkerPass();
    expect($generation->refresh()->not_before->toDateTimeString())->toBe('2026-11-21 06:12:00');

    $this->travelTo('2026-11-21 06:12:00');
    providerWorkerPass();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Failed)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::StepFailed)
        ->and($pipeline->calls('writing'))->toBe(3);
    Sleep::assertNeverSlept();
});

it('lets the other generations pass while one waits after an outage', function () {
    $waiting = BlogGeneration::factory()->running()->create(['lock_owner' => null, 'locked_until' => null, 'not_before' => now()->addMinutes(5)]);
    $next = BlogGeneration::factory()->create();

    expect(app(PendingBlogGenerationRepository::class)->nextId(CarbonImmutable::now()))->toBe($next->id);
});

it('relaunches once, an hour later, a scheduled generation that a passing outage made fail', function () {
    $pipeline = FakeGenerationPipeline::bind()->on('writing', fn () => throw transientFailure());
    $generation = BlogGeneration::factory()->scheduled()->create();

    foreach (['06:00', '06:02', '06:12'] as $time) {
        $this->travelTo("2026-11-21 {$time}:00");
        providerWorkerPass();
    }

    $generation->refresh();
    expect($generation->status)->toBe(BlogGenerationStatus::Queued)
        ->and($generation->not_before->toDateTimeString())->toBe('2026-11-21 07:12:00')
        ->and($generation->payload[RunBlogGenerationAction::AUTO_RELAUNCHED])->toBeTrue()
        ->and($generation->steps->firstWhere('step', 'topic')->status)->toBe(BlogGenerationStepStatus::Completed)
        ->and($generation->steps->firstWhere('step', 'writing')->attempt)->toBe(0);

    foreach (['07:12', '07:14', '07:24'] as $time) {
        $this->travelTo("2026-11-21 {$time}:00");
        providerWorkerPass();
    }

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Failed)
        ->and($pipeline->calls('writing'))->toBe(6)
        ->and($pipeline->calls('topic'))->toBe(1);
});

it('stops a generation stopped by hand during a step, and does not wait for a pending retry to do so', function () {
    FakeGenerationPipeline::bind()->on('writing', fn () => throw BlogAiProviderException::cancelled('writing'));
    $stoppedDuringStep = BlogGeneration::factory()->create();

    providerWorkerPass();

    expect($stoppedDuringStep->refresh()->status)->toBe(BlogGenerationStatus::Cancelled)
        ->and($stoppedDuringStep->outcome_reason)->toBe(BlogGenerationOutcomeReason::CancelledByUser);

    $waiting = BlogGeneration::factory()->running()->create(['lock_owner' => null, 'locked_until' => null, 'not_before' => now()->addMinutes(10), 'cancel_requested_at' => now()]);

    providerWorkerPass();

    expect($waiting->refresh()->status)->toBe(BlogGenerationStatus::Cancelled);
});

// --- Modeles figes au lancement, relance

it('runs a generation with the models of its launch, whatever the settings say later', function () {
    BlogAi::saveSettings(['models' => ['writing' => 'openai/gpt-6-luna']]);
    $usedModel = null;
    FakeGenerationPipeline::bind()->on('writing', function (GenerationStepContext $context) use (&$usedModel): GenerationStepResult {
        $usedModel = $context->settings->modelFor('writing');

        return GenerationStepResult::continue(['ok' => true]);
    });
    app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData);
    BlogAi::saveSettings(['models' => ['writing' => 'anthropic/claude-sonnet-5.5']]);

    providerWorkerPass();

    expect($usedModel)->toBe('openai/gpt-6-luna');
});

it('relaunches with the models of the moment, keeping the higher of the two announced costs', function (int $announcedHigh, bool $keepsOld) {
    $settings = BlogAi::saveSettings(['models' => ['writing' => 'anthropic/claude-sonnet-5.5']]);
    $generation = BlogGeneration::factory()->failed()->create([
        'payload' => ['models' => [...$settings->models, 'writing' => 'openai/gpt-6-luna'], 'effort' => 'medium', 'length' => ['words' => 1_000]],
        'forecast_low_microusd' => 1,
        'forecast_high_microusd' => $announcedHigh,
    ]);

    app(RetryBlogGenerationAction::class)->execute($generation->id);

    $generation->refresh();
    expect($generation->status)->toBe(BlogGenerationStatus::Queued)
        ->and($generation->payload['models']['writing'])->toBe('anthropic/claude-sonnet-5.5')
        ->and($generation->forecast_high_microusd === $announcedHigh)->toBe($keepsOld)
        ->and($generation->forecast_high_microusd)->toBeGreaterThanOrEqual($announcedHigh);
})->with([
    'the new forecast is higher' => [1, false],
    'the old one is higher' => [50_000_000, true],
]);

it('redoes the images of a generation relaunched after the purge deleted them, and keeps them otherwise', function (bool $purged, BlogGenerationStepStatus $expected) {
    // Controle du 20/11 : les images attendent l'assemblage sans article, et la purge les efface au bout de 24 heures.
    $generation = BlogGeneration::factory()->failed()->create();
    $image = BlogImage::factory()->create(['blog_post_id' => null]);
    $images = BlogGenerationStep::factory()->for($generation, 'generation')->named('images', 13)->completed()
        ->create(['output' => ['mode' => 'stock', 'images' => [['number' => 0, 'image_id' => $image->id]], 'warnings' => []]]);

    if ($purged) {
        BlogImage::query()->whereKey($image->id)->delete();
    }

    app(RetryBlogGenerationAction::class)->execute($generation->id);

    expect($images->refresh()->status)->toBe($expected);
})->with([
    'images purged' => [true, BlogGenerationStepStatus::Pending],
    'images still there' => [false, BlogGenerationStepStatus::Completed],
]);
