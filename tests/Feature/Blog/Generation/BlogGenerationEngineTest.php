<?php

use App\Actions\Blog\Generation\CancelBlogGenerationAction;
use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Blog\Generation\GenerationFindings;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogTopicKind;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogGenerationException;
use App\Mail\BlogGenerationBatchFinished;
use App\Mail\BlogSpendLimitReached;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Models\BlogPost;
use App\Repositories\Blog\BlogGenerationLockRepository;
use App\Repositories\Blog\PendingBlogGenerationRepository;
use App\Services\Blog\Generation\UnavailableGenerationPipeline;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\Support\BlogAi;
use Tests\Support\FakeGenerationPipeline;

/*
 | Moteur des generations : une generation est prise par un worker, ses etapes s'executent dans l'ordre et
 | sont enregistrees une a une, les echecs sont reessayes, une coupure se reprend. Les etapes sont des
 | etapes de test (aucun modele, aucune attente reelle).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
});

/** Un passage de worker : prend la plus ancienne generation a traiter et la traite. */
function generationWorkerPass(string $worker = 'worker-a'): bool
{
    return app(ProcessNextBlogGenerationAction::class)->execute($worker);
}

it('runs every step in order, records each one and completes the generation', function () {
    FakeGenerationPipeline::bind();
    $generation = BlogGeneration::factory()->create();

    expect(generationWorkerPass())->toBeTrue();

    $generation->refresh();

    expect($generation->status)->toBe(BlogGenerationStatus::Completed)
        ->and($generation->outcome_reason)->toBeNull()
        ->and($generation->current_step)->toBeNull()
        ->and($generation->locked_until)->toBeNull()
        ->and($generation->lock_owner)->toBeNull()
        ->and($generation->started_at)->not->toBeNull()
        ->and($generation->finished_at)->not->toBeNull()
        ->and($generation->cost_microusd)->toBe(3 * FakeGenerationPipeline::STEP_COST)
        ->and($generation->steps->pluck('step')->all())->toBe(['topic', 'writing', 'assembly'])
        ->and($generation->steps->pluck('position')->all())->toBe([1, 2, 3])
        ->and($generation->steps->pluck('attempt')->all())->toBe([1, 1, 1])
        ->and($generation->steps->every(fn (BlogGenerationStep $step): bool => $step->status === BlogGenerationStepStatus::Completed))->toBeTrue()
        ->and($generation->steps->first()->model)->toBe('test/model')
        ->and($generation->steps->first()->input_tokens)->toBe(100)
        ->and($generation->steps->first()->output)->toBe(['step' => 'topic']);
});

it('has nothing to do when no generation is waiting', function () {
    FakeGenerationPipeline::bind();
    BlogGeneration::factory()->completed()->create();

    expect(generationWorkerPass())->toBeFalse();
});

it('hands each step the request and what the previous steps produced', function () {
    $seen = null;
    FakeGenerationPipeline::bind()->on('writing', function (GenerationStepContext $context) use (&$seen): GenerationStepResult {
        $seen = $context;

        return GenerationStepResult::continue();
    });
    $generation = BlogGeneration::factory()->create(['topic_hint' => 'Etsy labels', 'instructions' => 'Short', 'payload' => ['post_id' => 7]]);

    generationWorkerPass();

    expect($seen->generationId)->toBe($generation->id)
        ->and($seen->topicHint)->toBe('Etsy labels')
        ->and($seen->instructions)->toBe('Short')
        ->and($seen->payload)->toBe(['post_id' => 7])
        ->and($seen->output('topic'))->toBe(['step' => 'topic'])
        ->and($seen->output('assembly'))->toBe([])
        ->and($seen->attempt)->toBe(1)
        ->and($seen->settings->enabled)->toBeFalse();
});

it('keeps on the generation the topic a step chose and the draft a step created, without the steps touching it', function () {
    $post = BlogPost::factory()->fromAi()->create();
    FakeGenerationPipeline::bind()
        ->on('topic', fn (): GenerationStepResult => GenerationStepResult::continue(findings: new GenerationFindings(
            topicTitle: 'Adding an economic operator on Etsy',
            topicSummary: 'A guide for UK sellers.',
            topicKind: BlogTopicKind::Evergreen,
        )))
        // Une etape qui ne trouve rien ne vide pas ce qu'une autre a trouve.
        ->on('writing', fn (): GenerationStepResult => GenerationStepResult::continue(findings: new GenerationFindings))
        ->on('assembly', fn (): GenerationStepResult => GenerationStepResult::continue(findings: new GenerationFindings(postId: $post->id)));
    $generation = BlogGeneration::factory()->create();

    generationWorkerPass();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed)
        ->and($generation->topic_title)->toBe('Adding an economic operator on Etsy')
        ->and($generation->topic_summary)->toBe('A guide for UK sellers.')
        ->and($generation->topic_kind)->toBe(BlogTopicKind::Evergreen)
        ->and($generation->blog_post_id)->toBe($post->id);
});

it('keeps the topic a step turned down as a duplicate, so the history says what was proposed', function () {
    FakeGenerationPipeline::bind()->on('topic', fn (): GenerationStepResult => GenerationStepResult::stop(
        BlogGenerationOutcomeReason::DuplicateTopic,
        findings: new GenerationFindings(topicTitle: str_repeat('Labels ', 60)),
    ));
    $generation = BlogGeneration::factory()->create();

    generationWorkerPass();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Abandoned)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::DuplicateTopic)
        // Coupe a la longueur de la colonne.
        ->and(mb_strlen($generation->topic_title))->toBe(300);
});

it('stops without an error when a step finds nothing worth writing', function () {
    $pipeline = FakeGenerationPipeline::bind()
        ->on('topic', fn (): GenerationStepResult => GenerationStepResult::stop(BlogGenerationOutcomeReason::NoRelevantTopic));
    $generation = BlogGeneration::factory()->create();

    generationWorkerPass();

    $generation->refresh();

    expect($generation->status)->toBe(BlogGenerationStatus::Abandoned)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::NoRelevantTopic)
        ->and($generation->last_error)->toBeNull()
        ->and($generation->lock_owner)->toBeNull()
        ->and($pipeline->calls('topic'))->toBe(1)
        ->and($pipeline->calls('writing'))->toBe(0);
});

it('retries a failing step and goes on once it succeeds, leaving the failed attempt in the server log', function () {
    Log::spy();
    $pipeline = FakeGenerationPipeline::bind()->on('writing', function (GenerationStepContext $context): GenerationStepResult {
        if ($context->attempt === 1) {
            throw new RuntimeException('Temporary provider error.');
        }

        return GenerationStepResult::continue(['ok' => true]);
    });
    $generation = BlogGeneration::factory()->create();

    generationWorkerPass();

    $writing = $generation->refresh()->steps->firstWhere('step', 'writing');

    expect($generation->status)->toBe(BlogGenerationStatus::Completed)
        ->and($writing->status)->toBe(BlogGenerationStepStatus::Completed)
        ->and($writing->attempt)->toBe(2)
        ->and($writing->error)->toBeNull()
        ->and($pipeline->calls('writing'))->toBe(2)
        ->and($pipeline->calls('assembly'))->toBe(1);
    Sleep::assertSleptTimes(1);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'will be retried')
        && $context['generation_id'] === $generation->id
        && $context['step'] === 'writing'
        && $context['attempt'] === 1
        && $context['exception']->getMessage() === 'Temporary provider error.');
});

it('fails the generation after three attempts of a step, without running the next ones', function () {
    $pipeline = FakeGenerationPipeline::bind()->on('writing', fn () => throw new RuntimeException('Provider is down.'));
    $generation = BlogGeneration::factory()->create();

    generationWorkerPass();

    $generation->refresh();
    $writing = $generation->steps->firstWhere('step', 'writing');

    expect($generation->status)->toBe(BlogGenerationStatus::Failed)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::StepFailed)
        ->and($generation->last_error)->toBe('Provider is down.')
        ->and($generation->finished_at)->not->toBeNull()
        ->and($generation->lock_owner)->toBeNull()
        ->and($generation->cost_microusd)->toBe(FakeGenerationPipeline::STEP_COST)
        ->and($writing->status)->toBe(BlogGenerationStepStatus::Failed)
        ->and($writing->attempt)->toBe(3)
        ->and($writing->error)->toBe('Provider is down.')
        ->and($pipeline->calls('writing'))->toBe(3)
        ->and($pipeline->calls('assembly'))->toBe(0)
        ->and($generation->steps)->toHaveCount(2);
});

it('does not retry when the provider credit is exhausted, and says so once a month', function () {
    $pipeline = FakeGenerationPipeline::bind()
        ->on('writing', fn () => throw BlogAiProviderException::creditExhausted(usage: new GenerationUsage(costMicrousd: 500)));
    $first = BlogGeneration::factory()->create();
    $second = BlogGeneration::factory()->create();

    generationWorkerPass();
    generationWorkerPass();

    expect($first->refresh()->status)->toBe(BlogGenerationStatus::Failed)
        ->and($first->outcome_reason)->toBe(BlogGenerationOutcomeReason::CreditExhausted)
        ->and($first->cost_microusd)->toBe(FakeGenerationPipeline::STEP_COST + 500)
        ->and($second->refresh()->outcome_reason)->toBe(BlogGenerationOutcomeReason::CreditExhausted)
        ->and($pipeline->calls('writing'))->toBe(2);
    Sleep::assertNeverSlept();
    Mail::assertSent(BlogSpendLimitReached::class, 1);
    Mail::assertSent(BlogSpendLimitReached::class, fn (BlogSpendLimitReached $mail): bool => $mail->hasTo(config('festilaw.notification_email')));
});

it('keeps what the failed attempts of a step consumed', function () {
    FakeGenerationPipeline::bind()->on('writing', function (GenerationStepContext $context): GenerationStepResult {
        if ($context->attempt < 3) {
            throw BlogAiProviderException::callFailed('writing', new RuntimeException('Timeout.'), new GenerationUsage(
                model: 'test/model', inputTokens: 40, costMicrousd: 700,
            ));
        }

        return GenerationStepResult::continue([], new GenerationUsage(model: 'test/model', inputTokens: 100, outputTokens: 50, costMicrousd: 10_000));
    });
    $generation = BlogGeneration::factory()->create();

    generationWorkerPass();

    $writing = $generation->refresh()->steps->firstWhere('step', 'writing');

    expect($writing->attempt)->toBe(3)
        ->and($writing->input_tokens)->toBe(180)
        ->and($writing->output_tokens)->toBe(50)
        ->and($writing->cost_microusd)->toBe(11_400)
        ->and($generation->cost_microusd)->toBe(2 * FakeGenerationPipeline::STEP_COST + 11_400);
});

it('resumes an interrupted generation at the first unfinished step, without redoing the others', function () {
    $pipeline = FakeGenerationPipeline::bind();
    $generation = BlogGeneration::factory()->interrupted()->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed(30_000)->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('writing', 2)->running(attempt: 1)->create();

    expect(generationWorkerPass('worker-b'))->toBeTrue();

    $generation->refresh();

    expect($generation->status)->toBe(BlogGenerationStatus::Completed)
        ->and($pipeline->calls('topic'))->toBe(0)
        ->and($pipeline->calls('writing'))->toBe(1)
        ->and($pipeline->calls('assembly'))->toBe(1)
        ->and($generation->steps->firstWhere('step', 'writing')->attempt)->toBe(2)
        ->and($generation->cost_microusd)->toBe(30_000 + 2 * FakeGenerationPipeline::STEP_COST);
});

it('gives up a step that was interrupted on its last attempt', function () {
    $pipeline = FakeGenerationPipeline::bind();
    $generation = BlogGeneration::factory()->interrupted()->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->running(attempt: 3)->create();

    generationWorkerPass();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Failed)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::StepFailed)
        ->and($pipeline->calls('topic'))->toBe(0);
});

it('lets only one worker take a generation', function () {
    $generation = BlogGeneration::factory()->create();
    $locks = app(BlogGenerationLockRepository::class);
    $now = CarbonImmutable::now();

    expect($locks->claim($generation->id, 'worker-a', $now, $now->addMinute()))->toBeTrue()
        ->and($locks->claim($generation->id, 'worker-b', $now, $now->addMinute()))->toBeFalse()
        ->and($generation->refresh()->lock_owner)->toBe('worker-a')
        ->and($generation->status)->toBe(BlogGenerationStatus::Running)
        ->and($locks->extend($generation->id, 'worker-b', $now->addMinutes(9)))->toBeFalse()
        // Prolonger deux fois jusqu'a la meme heure reste une detention (MySQL ne modifie alors aucune ligne).
        ->and($locks->extend($generation->id, 'worker-a', $now->addMinutes(2)))->toBeTrue()
        ->and($locks->extend($generation->id, 'worker-a', $now->addMinutes(2)))->toBeTrue();
});

it('processes one generation at a time, the oldest first, and picks up one whose worker died', function () {
    $pending = app(PendingBlogGenerationRepository::class);
    $now = CarbonImmutable::now();

    $running = BlogGeneration::factory()->running()->create();
    $queued = BlogGeneration::factory()->create();

    expect($pending->nextId($now))->toBeNull();

    $running->update(['locked_until' => $now->subSecond()]);

    expect($pending->nextId($now))->toBe($running->id);

    $running->update(['status' => BlogGenerationStatus::Completed, 'locked_until' => null]);

    expect($pending->nextId($now))->toBe($queued->id);
});

it('drops the result of a step when another worker took the generation over meanwhile', function () {
    $generation = BlogGeneration::factory()->create();
    $pipeline = FakeGenerationPipeline::bind()->on('topic', function () use ($generation): GenerationStepResult {
        // Le verrou de worker-a a expire pendant l'etape et worker-b a repris la generation.
        BlogGeneration::query()->whereKey($generation->id)->update(['lock_owner' => 'worker-b', 'locked_until' => now()->addMinutes(5)]);

        return GenerationStepResult::continue(['from' => 'worker-a']);
    });

    generationWorkerPass('worker-a');

    $generation->refresh();

    expect($generation->status)->toBe(BlogGenerationStatus::Running)
        ->and($generation->lock_owner)->toBe('worker-b')
        ->and($generation->steps->firstWhere('step', 'topic')->status)->toBe(BlogGenerationStepStatus::Running)
        ->and($pipeline->calls('writing'))->toBe(0);
    Mail::assertNothingSent();
});

it('stops at once when someone stops it during a step, drops the result of that step and keeps its cost', function () {
    $generation = BlogGeneration::factory()->create();
    $pipeline = FakeGenerationPipeline::bind()->on('topic', function () use ($generation): GenerationStepResult {
        // Arret pendant l'appel au fournisseur : l'appel finit quand meme, et il a ete facture.
        app(CancelBlogGenerationAction::class)->execute($generation->id);

        return GenerationStepResult::continue(['title' => 'Late answer'], new GenerationUsage(model: 'm', inputTokens: 1_000, costMicrousd: 4_000));
    });

    generationWorkerPass();

    $topic = $generation->refresh()->steps->firstWhere('step', 'topic');

    expect($generation->status)->toBe(BlogGenerationStatus::Cancelled)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::CancelledByUser)
        ->and($topic->status)->toBe(BlogGenerationStepStatus::Failed)
        ->and($topic->output)->toBeNull()
        ->and($topic->cost_microusd)->toBe(4_000)
        ->and($generation->cost_microusd)->toBe(4_000)
        ->and($pipeline->calls('research'))->toBe(0);
    Mail::assertNothingSent();
});

it('stops before the next step once the generation has spent its cap per article, without a draft', function () {
    BlogAi::saveSettings(['article_spend_cap_cents' => 15]);
    $generation = BlogGeneration::factory()->create();
    $pipeline = FakeGenerationPipeline::bind()->on('topic', fn (): GenerationStepResult => GenerationStepResult::continue([], new GenerationUsage(model: 'm', costMicrousd: 160_000)));

    generationWorkerPass();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Abandoned)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::ArticleSpendCapReached)
        ->and($generation->cost_microusd)->toBe(160_000)
        ->and($generation->lock_owner)->toBeNull()
        ->and($pipeline->calls('writing'))->toBe(0);
});

it('stops retrying a failing step once the cap per article is spent', function () {
    BlogAi::saveSettings(['article_spend_cap_cents' => 10]);
    $generation = BlogGeneration::factory()->create();
    $pipeline = FakeGenerationPipeline::bind()->on('topic', fn (): GenerationStepResult => throw BlogAiProviderException::callFailed(
        'topic', new RuntimeException('Unusable answer.'), new GenerationUsage(model: 'm', costMicrousd: 60_000),
    ));

    generationWorkerPass();

    // Deux essais (0,12 $) puis plus rien : le troisieme aurait depasse le plafond de 0,10 $.
    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Abandoned)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::ArticleSpendCapReached)
        ->and($pipeline->calls('topic'))->toBe(2)
        ->and($generation->cost_microusd)->toBe(120_000);
});

it('gives the generation back, unfinished, while the site is in maintenance', function () {
    $pipeline = FakeGenerationPipeline::bind();
    $generation = BlogGeneration::factory()->create();
    $maintenance = Mockery::mock(MaintenanceMode::class);
    $maintenance->shouldReceive('active')->andReturn(true, false);
    $this->app->instance(MaintenanceMode::class, $maintenance);

    generationWorkerPass();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Running)
        ->and($generation->lock_owner)->toBeNull()
        ->and($generation->locked_until)->toBeNull()
        ->and($pipeline->calls('topic'))->toBe(0);

    generationWorkerPass();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed);
});

it('fails cleanly, instead of looping, when the pipeline cannot be used', function () {
    $this->app->instance(GenerationPipeline::class, new UnavailableGenerationPipeline(BlogGenerationException::unknownPipeline('nope')));
    $generation = BlogGeneration::factory()->create();

    generationWorkerPass();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Failed)
        ->and($generation->last_error)->toContain('Unknown blog generation pipeline [nope]')
        ->and($generation->lock_owner)->toBeNull()
        ->and(generationWorkerPass())->toBeFalse();
});

it('hands the generation over between two steps once its worker has lived long enough', function () {
    $pipeline = FakeGenerationPipeline::bind();
    $generation = BlogGeneration::factory()->create();
    $alreadyDue = CarbonImmutable::now();
    $process = fn (string $worker): bool => app(ProcessNextBlogGenerationAction::class)->execute($worker, $alreadyDue);

    // Chaque worker fait au moins une etape, puis rend la generation au suivant, sans la terminer.
    expect($process('worker-a'))->toBeTrue();

    $generation->refresh();

    expect($generation->status)->toBe(BlogGenerationStatus::Running)
        ->and($generation->lock_owner)->toBeNull()
        ->and($generation->locked_until)->toBeNull()
        ->and($generation->finished_at)->toBeNull()
        ->and($pipeline->calls('topic'))->toBe(1)
        ->and($pipeline->calls('writing'))->toBe(0);
    Mail::assertNothingSent();

    $process('worker-b');
    $process('worker-c');

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed)
        ->and($pipeline->calls)->toBe(['topic' => 1, 'writing' => 1, 'assembly' => 1])
        ->and($generation->cost_microusd)->toBe(3 * FakeGenerationPipeline::STEP_COST);
    Mail::assertSent(BlogGenerationBatchFinished::class, 1);
});

it('keeps the generation until the end while its worker is young enough', function () {
    FakeGenerationPipeline::bind();
    $generation = BlogGeneration::factory()->create();

    app(ProcessNextBlogGenerationAction::class)->execute('worker-a', CarbonImmutable::now()->addMinute());

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed);
});

it('keeps the short account a step gives of its work, cut to a couple of sentences', function () {
    FakeGenerationPipeline::bind()
        ->on('topic', fn (): GenerationStepResult => GenerationStepResult::continue(['step' => 'topic'], null, '  Sellers keep asking about labels.  '))
        ->on('writing', fn (): GenerationStepResult => GenerationStepResult::continue(['step' => 'writing'], null, str_repeat('a', 900)));
    $generation = BlogGeneration::factory()->create();

    generationWorkerPass();

    $steps = $generation->steps()->get()->keyBy('step');

    expect($steps['topic']->summary)->toBe('Sellers keep asking about labels.')
        // Une ou deux phrases, pas un rapport : 600 caracteres, puis des points de suspension.
        ->and($steps['writing']->summary)->toBe(str_repeat('a', 600).'...')
        // Une etape qui ne dit rien ne laisse rien.
        ->and($steps['assembly']->summary)->toBeNull()
        ->and($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed);
});
