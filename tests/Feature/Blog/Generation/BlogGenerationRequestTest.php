<?php

use App\Actions\Blog\Generation\CancelBlogGenerationAction;
use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Generation\QueueBlogGenerationAction;
use App\Actions\Blog\Generation\RetryBlogGenerationAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogGenerationException;
use App\Mail\BlogGenerationBatchFinished;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\Blog\Generation\UnavailableGenerationPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\Support\BlogAi;
use Tests\Support\FakeGenerationPipeline;

/*
 | Ce qu'une personne fait d'une generation : la demander, la relancer apres un echec, l'arreter. Et le
 | message qui part quand un lancement est termine (un seul par lancement).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
    FakeGenerationPipeline::bind();
});

it('queues a generation requested by someone, with what they imposed', function () {
    $user = User::factory()->create();

    $id = app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData(
        topicHint: '  Labels on Etsy  ',
        instructions: '   ',
        requestedBy: $user->id,
    ));

    $generation = BlogGeneration::query()->sole();

    expect($generation->id)->toBe($id)
        ->and($generation->type)->toBe(BlogGenerationType::Article)
        ->and($generation->trigger)->toBe(BlogGenerationTrigger::Manual)
        ->and($generation->status)->toBe(BlogGenerationStatus::Queued)
        ->and($generation->topic_hint)->toBe('Labels on Etsy')
        ->and($generation->instructions)->toBeNull()
        ->and($generation->requested_by)->toBe($user->id)
        ->and($generation->batch_uuid)->not->toBeEmpty()
        ->and($generation->started_at)->toBeNull();
});

it('refuses a request once the monthly spend cap is reached, and says why', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    BlogGeneration::factory()->completed(costMicrousd: 1_000_000)->create();

    expect(fn () => app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData))
        ->toThrow(function (BlogGenerationException $e): void {
            expect($e->getUserMessage())->toContain('plafond de dépense');
        });

    expect(BlogGeneration::count())->toBe(1);
});

it('does not hold a manual request back because generated drafts are waiting', function () {
    BlogAi::saveSettings(['pending_drafts_cap' => 1]);
    BlogPost::factory()->fromAi()->count(3)->create();

    app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData);

    expect(BlogGeneration::count())->toBe(1);
});

it('counts only the spend of the current month against the cap', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    BlogGeneration::factory()->completed(costMicrousd: 5_000_000)->create(['created_at' => now()->subMonthNoOverflow()]);

    app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData);

    expect(BlogGeneration::count())->toBe(2);
});

it('refuses a request at once when the pipeline cannot be used', function () {
    $this->app->instance(GenerationPipeline::class, new UnavailableGenerationPipeline(BlogGenerationException::simulatedPipelineInProduction()));

    expect(fn () => app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData))
        ->toThrow(BlogGenerationException::class);

    expect(BlogGeneration::count())->toBe(0);
});

it('queues a generation from the command line', function () {
    $this->artisan('festilaw:blog-generate', ['--topic' => 'CE marking'])
        ->expectsOutputToContain('mise en file')
        ->assertSuccessful();

    expect(BlogGeneration::query()->sole()->topic_hint)->toBe('CE marking');
});

it('restarts a failed generation from the step that failed, keeping what was done', function () {
    $pipeline = FakeGenerationPipeline::bind();
    $generation = BlogGeneration::factory()->failed()->create(['batch_notified_at' => now()]);
    $oldBatch = $generation->batch_uuid;
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed(30_000)->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('writing', 2)->failed(attempt: 3)->create();

    app(RetryBlogGenerationAction::class)->execute($generation->id);

    $generation->refresh();
    $writing = $generation->steps->firstWhere('step', 'writing');

    expect($generation->status)->toBe(BlogGenerationStatus::Queued)
        ->and($generation->outcome_reason)->toBeNull()
        ->and($generation->last_error)->toBeNull()
        ->and($generation->finished_at)->toBeNull()
        ->and($generation->batch_uuid)->not->toBe($oldBatch)
        ->and($generation->batch_notified_at)->toBeNull()
        ->and($writing->status)->toBe(BlogGenerationStepStatus::Pending)
        ->and($writing->attempt)->toBe(0)
        ->and($writing->error)->toBeNull()
        ->and($generation->steps->firstWhere('step', 'topic')->status)->toBe(BlogGenerationStepStatus::Completed);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-a');

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed)
        ->and($pipeline->calls('topic'))->toBe(0)
        ->and($pipeline->calls('writing'))->toBe(1);
    Mail::assertSent(BlogGenerationBatchFinished::class, 1);
});

it('only restarts a generation that failed', function (string $state) {
    $generation = BlogGeneration::factory()->{$state}()->create();

    expect(fn () => app(RetryBlogGenerationAction::class)->execute($generation->id))
        ->toThrow(BlogGenerationException::class);
})->with(['completed', 'running', 'abandoned', 'cancelled']);

it('refuses to restart once the monthly spend cap is reached', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    $generation = BlogGeneration::factory()->failed()->create(['cost_microusd' => 1_000_000]);

    expect(fn () => app(RetryBlogGenerationAction::class)->execute($generation->id))
        ->toThrow(BlogGenerationException::class);

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Failed);
});

it('stops a waiting generation at once', function () {
    $generation = BlogGeneration::factory()->create();

    expect(app(CancelBlogGenerationAction::class)->execute($generation->id))->toBeTrue()
        ->and($generation->refresh()->status)->toBe(BlogGenerationStatus::Cancelled)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::CancelledByUser)
        ->and($generation->finished_at)->not->toBeNull()
        ->and(app(ProcessNextBlogGenerationAction::class)->execute('worker-a'))->toBeFalse()
        ->and($generation->batch_notified_at)->not->toBeNull();
    Mail::assertNothingSent();
});

it('still sends the message of a batch whose last waiting generation is stopped, when another one ran', function () {
    $draft = BlogPost::factory()->create();
    $ran = BlogGeneration::factory()->scheduled()->completed()->create(['blog_post_id' => $draft->id]);
    $waiting = BlogGeneration::factory()->scheduled()->inBatch($ran->batch_uuid)->create();

    app(CancelBlogGenerationAction::class)->execute($waiting->id);

    Mail::assertSent(BlogGenerationBatchFinished::class, 1);
    Mail::assertSent(BlogGenerationBatchFinished::class, fn (BlogGenerationBatchFinished $mail): bool => count($mail->rows) === 2
        && $mail->rows[0]['succeeded'] === true
        && $mail->rows[0]['draftUrl'] === route('admin.blog.posts.edit', ['post' => $draft->id])
        && $mail->rows[1]['outcome'] === 'Arrêtée au back-office'
        && $mail->rows[1]['url'] === route('admin.blog.generation.show', ['generation' => $waiting->id])
        && $mail->rows[1]['draftUrl'] === null
        && $mail->summary() === 'Sur 2 générations, 1 a réussi et 1 n\'a pas donné d\'article.');
});

it('asks a running generation to stop, without stopping it itself', function () {
    $generation = BlogGeneration::factory()->running()->create();

    expect(app(CancelBlogGenerationAction::class)->execute($generation->id))->toBeFalse()
        ->and($generation->refresh()->status)->toBe(BlogGenerationStatus::Running)
        ->and($generation->cancel_requested_at)->not->toBeNull();
    Mail::assertNothingSent();
});

it('cannot stop a generation that is already over, or that does not exist', function () {
    $generation = BlogGeneration::factory()->completed()->create();

    expect(fn () => app(CancelBlogGenerationAction::class)->execute($generation->id))->toThrow(BlogGenerationException::class)
        ->and(fn () => app(CancelBlogGenerationAction::class)->execute(999_999))->toThrow(BlogGenerationException::class)
        ->and(fn () => app(RetryBlogGenerationAction::class)->execute(999_999))->toThrow(BlogGenerationException::class);
});

it('sends one message when a requested generation is over, to the recipient of the settings', function () {
    BlogAi::saveSettings(['notification_email' => 'owner@example.com']);
    $generation = BlogGeneration::factory()->create(['topic_hint' => 'Labels on Etsy']);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-a');

    expect($generation->refresh()->batch_notified_at)->not->toBeNull();
    Mail::assertSent(BlogGenerationBatchFinished::class, 1);
    Mail::assertSent(BlogGenerationBatchFinished::class, fn (BlogGenerationBatchFinished $mail): bool => $mail->hasTo('owner@example.com')
        && ! $mail->scheduled
        && $mail->rows === [[
            'subject' => 'Labels on Etsy',
            'outcome' => 'Terminée',
            'succeeded' => true,
            'failed' => false,
            'cost' => '0,03 $',
            'url' => route('admin.blog.generation.show', ['generation' => $generation->id]),
            'draftUrl' => null,
        ]]
        && $mail->totalCost === '0,03 $'
        && $mail->postsUrl === route('admin.blog.posts.index')
        && $mail->generationsUrl === route('admin.blog.generation.index'));
});

it('waits for the whole batch before sending its single message', function () {
    $pipeline = FakeGenerationPipeline::bind();
    $first = BlogGeneration::factory()->scheduled()->create();
    $second = BlogGeneration::factory()->scheduled()->inBatch($first->batch_uuid)->create();
    $pipeline->on('writing', function () use ($first) {
        if ($first->fresh()->status === BlogGenerationStatus::Running) {
            throw new RuntimeException('Provider is down.');
        }

        return GenerationStepResult::continue();
    });

    app(ProcessNextBlogGenerationAction::class)->execute('worker-a');

    expect($first->refresh()->status)->toBe(BlogGenerationStatus::Failed);
    Mail::assertNotSent(BlogGenerationBatchFinished::class);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-a');

    expect($second->refresh()->status)->toBe(BlogGenerationStatus::Completed);
    Mail::assertSent(BlogGenerationBatchFinished::class, 1);
    Mail::assertSent(BlogGenerationBatchFinished::class, fn (BlogGenerationBatchFinished $mail): bool => $mail->scheduled
        && count($mail->rows) === 2
        && $mail->rows[0]['succeeded'] === false
        && $mail->rows[0]['failed'] === true
        && $mail->rows[0]['outcome'] === 'Échec à l\'étape « Rédaction »'
        && $mail->rows[0]['url'] === route('admin.blog.generation.show', ['generation' => $first->id])
        && $mail->summary() === 'Sur 2 générations, 1 a réussi et 1 a échoué.'
        && $mail->rows[0]['subject'] === 'Sujet choisi par l\'IA'
        && $mail->rows[1]['succeeded'] === true);
});

it('renders the message of a finished batch: how many succeeded, where the others failed, where to go', function () {
    $succeeded = ['subject' => 'Labels on Etsy', 'outcome' => 'Terminée', 'succeeded' => true, 'failed' => false, 'cost' => '0,26 $', 'url' => 'https://example.test/admin/blog/generation/1', 'draftUrl' => 'https://example.test/admin/blog/posts/7/edit'];
    $failed = ['subject' => 'CE marking for toys', 'outcome' => 'Échec à l\'étape « Recherche »', 'succeeded' => false, 'failed' => true, 'cost' => '0,01 $', 'url' => 'https://example.test/admin/blog/generation/2', 'draftUrl' => null];
    $abandoned = ['subject' => 'GPSR basics', 'outcome' => 'Aucun sujet assez pertinent', 'succeeded' => false, 'failed' => false, 'cost' => '0,00 $', 'url' => 'https://example.test/admin/blog/generation/3', 'draftUrl' => null];
    $message = fn (array $rows): string => (new BlogGenerationBatchFinished(
        rows: $rows,
        scheduled: true,
        totalCost: '0,27 $',
        postsUrl: 'https://example.test/admin/blog/posts',
        generationsUrl: 'https://example.test/admin/blog/generation',
    ))->render();

    expect($message([$succeeded, $failed, $abandoned]))
        ->toContain('La génération automatique vient de se terminer')
        ->toContain('Sur 3 générations, 1 a réussi, 1 a échoué et 1 n')
        ->toContain('Labels on Etsy')
        ->toContain('étape « Recherche »')
        ->toContain('0,27 $')
        ->toContain('href="https://example.test/admin/blog/generation/2"')
        ->toContain('href="https://example.test/admin/blog/posts/7/edit"')
        ->toContain('href="https://example.test/admin/blog/posts"')
        ->toContain('href="https://example.test/admin/blog/generation"')
        ->toContain('contactez l\'assistance technique');

    // Rien n'a reussi : le bouton mene aux generations, pas aux articles. Une seule generation : pas de decompte.
    expect($message([$failed]))
        ->toContain('Ouvrir les générations')
        ->not->toContain('Ouvrir les articles')
        ->not->toContain('Sur 1 génération');

    // Tout a reussi : aucune invitation a relancer.
    expect($message([$succeeded]))->toContain('Ouvrir les articles')->not->toContain('assistance technique');
});
