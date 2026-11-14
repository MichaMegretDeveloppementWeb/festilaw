<?php

use App\Data\Blog\Generation\GenerationStepResult;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Models\BlogGeneration;
use App\Services\Blog\Generation\BlogGenerationWorkerRegistry;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Tests\Support\FakeGenerationPipeline;

/*
 | Worker des generations : la commande que le planificateur relance chaque minute. Elle reste a l'ecoute
 | (une verification toutes les 2 secondes), traite ce qui arrive, et s'arrete quand un worker plus recent
 | a pris le relais ou qu'aucune releve ne vient. Les attentes sont simulees et font avancer l'horloge du
 | test (reglages de config/blog.php : 2 s, releve verifiee toutes les 10 s, 90 s a vide au plus, generation
 | rendue entre deux etapes apres 60 s de vie).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Http::preventStrayRequests();
    $this->freezeTime();
    Sleep::fake(syncWithCarbon: true);
    FakeGenerationPipeline::bind();
});

it('processes a waiting generation in a single pass', function () {
    $generation = BlogGeneration::factory()->create();

    $this->artisan('festilaw:blog-generation-worker', ['--once' => true])
        ->expectsOutputToContain('1 working pass(es), 0 failed pass(es)')
        ->assertSuccessful();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed)
        ->and(app(BlogGenerationWorkerRegistry::class)->lastStartedAt())->not->toBeNull();
    Sleep::assertNeverSlept();
});

it('leaves no trace when there is nothing to do', function () {
    $this->artisan('festilaw:blog-generation-worker', ['--once' => true])
        ->doesntExpectOutputToContain('Blog generation worker')
        ->assertSuccessful();
});

it('keeps listening, then stops by itself when no newer worker takes over', function () {
    $this->artisan('festilaw:blog-generation-worker')->assertSuccessful();

    // 90 secondes a vide, une verification toutes les 2 secondes.
    Sleep::assertSleptTimes(45);
});

it('stops as soon as a newer worker has taken over', function () {
    $registry = app(BlogGenerationWorkerRegistry::class);
    $sleeps = 0;
    Sleep::whenFakingSleep(function () use ($registry, &$sleeps): void {
        if (++$sleeps === 3) {
            $registry->register('newer-worker');
        }
    });

    $this->artisan('festilaw:blog-generation-worker')->assertSuccessful();

    // Le nouveau worker s'inscrit a la 6e seconde ; l'ancien le voit a sa verification suivante (10e seconde).
    Sleep::assertSleptTimes(5);
    expect($registry->isCurrent('newer-worker'))->toBeTrue();
});

it('picks up a generation requested while it is listening', function () {
    $sleeps = 0;
    Sleep::whenFakingSleep(function () use (&$sleeps): void {
        if (++$sleeps === 2) {
            BlogGeneration::factory()->create();
        }
    });

    $this->artisan('festilaw:blog-generation-worker')
        ->expectsOutputToContain('1 working pass(es)')
        ->assertSuccessful();

    expect(BlogGeneration::query()->sole()->status)->toBe(BlogGenerationStatus::Completed);
    // 2 attentes avant la demande, puis 90 secondes a vide apres l'avoir traitee.
    Sleep::assertSleptTimes(47);
});

it('does not start anything while the site is in maintenance', function () {
    $generation = BlogGeneration::factory()->create();
    $maintenance = Mockery::mock(MaintenanceMode::class);
    $maintenance->shouldReceive('active')->andReturn(true);
    $this->app->instance(MaintenanceMode::class, $maintenance);

    $this->artisan('festilaw:blog-generation-worker')->assertSuccessful();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Queued);
    Sleep::assertNeverSlept();
});

it('reports a failing pass instead of dying, and says so with its exit code', function () {
    Log::spy();

    // Panne simulee : la table des generations disparait (la transaction du test la restaure).
    Schema::rename('blog_generations', 'blog_generations_unavailable');

    $this->artisan('festilaw:blog-generation-worker', ['--once' => true])
        ->expectsOutputToContain('0 working pass(es), 1 failed pass(es)')
        ->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'Blog generation worker pass failed'));

    Schema::rename('blog_generations_unavailable', 'blog_generations');
});

it('hands a long generation over to the next worker instead of living on', function () {
    $generation = BlogGeneration::factory()->create();
    $registry = app(BlogGenerationWorkerRegistry::class);
    FakeGenerationPipeline::bind()->on('topic', function () use ($registry): GenerationStepResult {
        // L'etape dure plus que la vie prevue du worker, et le worker de la minute suivante s'est inscrit.
        $this->travel(70)->seconds();
        $registry->register('next-worker');

        return GenerationStepResult::continue();
    });

    $this->artisan('festilaw:blog-generation-worker')
        ->expectsOutputToContain('1 working pass(es)')
        ->assertSuccessful();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Running)
        ->and($generation->lock_owner)->toBeNull()
        ->and($generation->locked_until)->toBeNull()
        ->and($generation->steps->pluck('step')->all())->toBe(['topic']);
    Sleep::assertNeverSlept();

    // Le worker suivant reprend a l'etape d'apres, sans refaire la premiere, et termine.
    $pipeline = FakeGenerationPipeline::bind();
    $this->artisan('festilaw:blog-generation-worker', ['--once' => true])->assertSuccessful();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed)
        ->and($pipeline->calls('topic'))->toBe(0)
        ->and($pipeline->calls('writing'))->toBe(1);
});

it('goes on by itself, one step at a time, when no newer worker shows up', function () {
    $generation = BlogGeneration::factory()->create();
    $pipeline = FakeGenerationPipeline::bind()->on('topic', function (): GenerationStepResult {
        $this->travel(70)->seconds();

        return GenerationStepResult::continue();
    });

    $this->artisan('festilaw:blog-generation-worker')
        ->expectsOutputToContain('3 working pass(es)')
        ->assertSuccessful();

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed)
        ->and($pipeline->calls('topic'))->toBe(1)
        ->and($pipeline->calls('writing'))->toBe(1)
        ->and($pipeline->calls('assembly'))->toBe(1);
});
