<?php

use App\Actions\Blog\Generation\CancelBlogGenerationAction;
use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Generation\QueueBlogGenerationAction;
use App\Actions\Blog\Generation\RetryBlogGenerationAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Enums\Blog\Generation\MonthlySpendLevel;
use App\Exceptions\Blog\BlogGenerationException;
use App\Mail\BlogGenerationBatchFinished;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\Blog\Generation\BlogGenerationBudget;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
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

it('still accepts a request above the monthly spend cap, which only warns', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    BlogGeneration::factory()->completed(costMicrousd: 1_000_000)->create();

    app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData);

    expect(BlogGeneration::count())->toBe(2);
});

it('launches an article whatever its estimated cost, and keeps the cost announced in the launch window', function () {
    // Decision de Micha du 18/11 : plus de plafond par article. Trois images generees et 2 000 mots partent aussi.
    $settings = BlogAi::saveSettings(['models' => ['writing' => 'anthropic/claude-sonnet-5.5']]);
    $images = new GenerationImageOptions(BlogAiImageMode::Generated, 3);

    $id = app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData(images: $images, articleWords: 2_000));

    $announced = app(BlogGenerationCostForecaster::class)->forSettings($settings, 2_000, $images);
    expect(BlogGeneration::query()->findOrFail($id))
        ->forecast_low_microusd->toBe($announced->lowMicrousd)
        ->forecast_high_microusd->toBe($announced->highMicrousd);
});

it('announces an assisted translation from the length of the text to translate', function () {
    $translation = static fn (int $words): int => app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData(
        type: BlogGenerationType::Translation,
        payload: ['translation' => ['article' => ['title' => 'Title', 'body' => str_repeat('word ', $words)]]],
    ));

    $short = BlogGeneration::query()->findOrFail($translation(200));
    $long = BlogGeneration::query()->findOrFail($translation(2_000));

    expect($short->forecast_high_microusd)->toBeGreaterThan(0)
        ->and($long->forecast_high_microusd)->toBeGreaterThan($short->forecast_high_microusd);
});

it('does not hold a manual request back because generated drafts are waiting', function () {
    BlogAi::saveSettings(['pending_drafts_cap' => 1]);
    BlogPost::factory()->fromAi()->count(3)->create();

    app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData);

    expect(BlogGeneration::count())->toBe(1);
});

it('counts only the spend of the current month against the monthly cap', function () {
    BlogGeneration::factory()->completed(costMicrousd: 5_000_000)->create(['created_at' => now()->subMonthNoOverflow()]);
    BlogGeneration::factory()->completed(costMicrousd: 300_000)->create();

    expect(app(BlogGenerationBudget::class)->spentThisMonthMicrousd())->toBe(300_000);
});

it('places the spend of the month against its cap, warning from 80 % and over it once passed', function (int $spent, MonthlySpendLevel $level) {
    expect(app(BlogGenerationBudget::class)->monthlyLevel($spent, 1_000_000))->toBe($level);
})->with([
    'under the warning' => [799_999, MonthlySpendLevel::Under],
    'at the warning' => [800_000, MonthlySpendLevel::Near],
    'at the cap' => [1_000_000, MonthlySpendLevel::Near],
    'over the cap' => [1_000_001, MonthlySpendLevel::Over],
]);

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

it('restarts a failed generation above the monthly spend cap, which only warns', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    $generation = BlogGeneration::factory()->failed()->create(['cost_microusd' => 1_000_000]);

    app(RetryBlogGenerationAction::class)->execute($generation->id);

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Queued);
});

it('stops a waiting generation at once', function () {
    $generation = BlogGeneration::factory()->create();

    app(CancelBlogGenerationAction::class)->execute($generation->id);

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Cancelled)
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
        && $mail->rows[1]['outcome'] === 'Arrêtée avant la fin'
        && $mail->rows[1]['url'] === route('admin.blog.generation.show', ['generation' => $waiting->id])
        && $mail->rows[1]['draftUrl'] === null
        && $mail->summary() === 'Sur 2 générations, 1 a réussi et 1 n\'a pas donné d\'article.');
});

it('stops a running generation at once, frees its lock and closes the step in progress', function () {
    $generation = BlogGeneration::factory()->running()->create(['current_step' => 'research']);
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->completed()->create();
    $running = BlogGenerationStep::factory()->for($generation, 'generation')->named('research', 2)->create(['status' => BlogGenerationStepStatus::Running]);

    app(CancelBlogGenerationAction::class)->execute($generation->id);

    // Aucun worker n'a a passer : la generation est arretee, et personne ne la reprendra.
    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Cancelled)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::CancelledByUser)
        ->and($generation->lock_owner)->toBeNull()
        ->and($generation->locked_until)->toBeNull()
        ->and($generation->current_step)->toBeNull()
        ->and($generation->finished_at)->not->toBeNull()
        ->and($running->refresh()->status)->toBe(BlogGenerationStepStatus::Failed)
        ->and($running->finished_at)->not->toBeNull()
        ->and(app(ProcessNextBlogGenerationAction::class)->execute('worker-a'))->toBeFalse();
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
            'cost' => dollarsText('0,0300 $'),
            'url' => route('admin.blog.generation.show', ['generation' => $generation->id]),
            'draftUrl' => null,
            'excerpt' => null,
            'sources' => null,
            'newCategory' => null,
        ]]
        && $mail->totalCost === dollarsText('0,0300 $')
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
    $succeeded = ['subject' => 'Labels on Etsy', 'outcome' => 'Terminée', 'succeeded' => true, 'failed' => false, 'cost' => '0,26 $', 'url' => 'https://example.test/admin/blog/generation/1', 'draftUrl' => 'https://example.test/admin/blog/posts/7/edit', 'newCategory' => 'Sécurité des produits'];
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
        ->toContain('Nouvelle catégorie « Sécurité des produits », créée pour cet article.')
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

it('gives a requested generation its own images, length, effort and models, or the default ones of the settings', function () {
    $settings = BlogAi::saveSettings(['image_mode' => 'generated', 'max_images' => 3, 'article_words' => 1_200, 'effort' => 'low']);

    $withDefaults = app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData);
    $withItsOwn = app(QueueBlogGenerationAction::class)->execute(new QueueBlogGenerationData(
        payload: ['post_id' => 7],
        images: new GenerationImageOptions(BlogAiImageMode::None, 1),
        articleWords: 9_000,
        effort: BlogEffortLevel::High,
    ));

    // Les reglages changent ensuite : les generations deja demandees gardent leurs images, leur longueur, leur
    // effort et leurs modeles (figes depuis le controle du 20/11).
    BlogAi::saveSettings(['image_mode' => 'stock', 'max_images' => 1, 'article_words' => 600, 'effort' => 'medium', 'models' => ['writing' => 'anthropic/claude-sonnet-5.5']]);

    // Une longueur hors des bornes est ramenee a la borne (2 000 mots au plus).
    expect(BlogGeneration::query()->findOrFail($withDefaults)->payload)->toEqual(['images' => ['mode' => 'generated', 'max' => 3], 'effort' => 'low', 'models' => $settings->models, 'length' => ['words' => 1_200]])
        ->and(BlogGeneration::query()->findOrFail($withItsOwn)->payload)->toEqual(['post_id' => 7, 'images' => ['mode' => 'none', 'max' => 1], 'effort' => 'high', 'models' => $settings->models, 'length' => ['words' => 2_000]]);
});

it('keeps who stopped a generation and when, whether it was waiting or already running', function () {
    $user = User::factory()->create();
    $waiting = BlogGeneration::factory()->create();
    $running = BlogGeneration::factory()->running()->create();

    app(CancelBlogGenerationAction::class)->execute($waiting->id, $user->id);
    app(CancelBlogGenerationAction::class)->execute($running->id, $user->id);

    expect($waiting->refresh()->cancel_requested_by)->toBe($user->id)
        ->and($waiting->cancel_requested_at)->not->toBeNull()
        ->and($running->refresh()->cancel_requested_by)->toBe($user->id)
        ->and($running->cancel_requested_at)->not->toBeNull();

    // Le compte disparait : la generation reste, sans nom.
    $user->delete();

    expect($waiting->refresh()->cancel_requested_by)->toBeNull();
});
