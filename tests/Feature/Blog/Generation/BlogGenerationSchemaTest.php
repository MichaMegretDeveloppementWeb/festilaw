<?php

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Models\BlogPost;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Tables des generations : valeurs par defaut, types rendus par les modeles, et ce que la suppression d'un
 | parent entraine. Les contraintes CHECK n'existent que sur MySQL (migrate:fresh sur une base jetable).
 */

uses(RefreshDatabase::class);

it('applies the column defaults and casts states and dates', function () {
    $generation = BlogGeneration::factory()->running()->create(['payload' => ['post_id' => 3]])->fresh();
    $step = BlogGenerationStep::factory()->for($generation, 'generation')->create()->fresh();

    expect($generation->type)->toBe(BlogGenerationType::Article)
        ->and($generation->trigger)->toBe(BlogGenerationTrigger::Manual)
        ->and($generation->status)->toBe(BlogGenerationStatus::Running)
        ->and($generation->outcome_reason)->toBeNull()
        ->and($generation->cost_microusd)->toBe(0)
        ->and($generation->payload)->toBe(['post_id' => 3])
        ->and($generation->locked_until)->toBeInstanceOf(CarbonImmutable::class)
        ->and($generation->started_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($step->status)->toBe(BlogGenerationStepStatus::Pending)
        ->and($step->attempt)->toBe(0)
        ->and($step->cost_microusd)->toBe(0)
        ->and($step->cost_is_estimated)->toBeFalse()
        ->and($step->output)->toBeNull()
        ->and($step->generation->is($generation))->toBeTrue();
});

it('builds every state the journal can show', function () {
    expect(BlogGeneration::factory()->scheduled()->create()->trigger)->toBe(BlogGenerationTrigger::Scheduled)
        ->and(BlogGeneration::factory()->completed(42)->create()->cost_microusd)->toBe(42)
        ->and(BlogGeneration::factory()->failed()->create()->outcome_reason)->toBe(BlogGenerationOutcomeReason::StepFailed)
        ->and(BlogGeneration::factory()->abandoned()->create()->status)->toBe(BlogGenerationStatus::Abandoned)
        ->and(BlogGeneration::factory()->cancelled()->create()->outcome_reason)->toBe(BlogGenerationOutcomeReason::CancelledByUser)
        ->and(BlogGeneration::factory()->interrupted()->create()->locked_until->isPast())->toBeTrue();
});

it('keeps one row per step of a generation, in the order of the pipeline', function () {
    $generation = BlogGeneration::factory()->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('writing', 2)->create();
    BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 1)->create();

    expect($generation->steps->pluck('step')->all())->toBe(['topic', 'writing'])
        ->and(fn () => BlogGenerationStep::factory()->for($generation, 'generation')->named('topic', 3)->create())
        ->toThrow(QueryException::class);
});

it('removes the steps with their generation, and keeps a generation whose post or requester is deleted', function () {
    $user = User::factory()->create();
    $post = BlogPost::factory()->create();
    $generation = BlogGeneration::factory()->completed()->create(['requested_by' => $user->id, 'blog_post_id' => $post->id]);
    BlogGenerationStep::factory()->for($generation, 'generation')->completed()->create();

    expect($generation->post->is($post))->toBeTrue()
        ->and($generation->requester->is($user))->toBeTrue();

    $post->delete();
    $user->delete();
    $generation->refresh();

    expect($generation->blog_post_id)->toBeNull()
        ->and($generation->requested_by)->toBeNull();

    $generation->delete();

    expect(BlogGenerationStep::count())->toBe(0);
});
