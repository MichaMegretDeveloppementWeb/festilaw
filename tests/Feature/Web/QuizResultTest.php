<?php

use App\Enums\Quiz\QuizOutcome;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('stores an anonymous quiz result and derives the concerned outcome', function () {
    postJson(route('quiz.result'), [
        'q1_based_outside_eu' => true,
        'q2_sells_to_eu' => true,
        'q3_sells_restricted' => false,
    ])->assertCreated();

    $result = QuizResult::sole();

    expect($result->outcome)->toBe(QuizOutcome::Concerned)
        ->and($result->q1_based_outside_eu)->toBeTrue()
        ->and($result->q2_sells_to_eu)->toBeTrue()
        ->and($result->q3_sells_restricted)->toBeFalse()
        ->and($result->submission_id)->toBeNull();
});

it('derives the excluded outcome when a restricted category is sold', function () {
    postJson(route('quiz.result'), [
        'q1_based_outside_eu' => true,
        'q2_sells_to_eu' => true,
        'q3_sells_restricted' => true,
    ])->assertCreated();

    expect(QuizResult::sole()->outcome)->toBe(QuizOutcome::Excluded);
});

it('derives the not-concerned outcome otherwise', function () {
    postJson(route('quiz.result'), [
        'q1_based_outside_eu' => false,
        'q2_sells_to_eu' => true,
        'q3_sells_restricted' => false,
    ])->assertCreated();

    expect(QuizResult::sole()->outcome)->toBe(QuizOutcome::NotConcerned);
});

it('validates the quiz answers before storing', function () {
    postJson(route('quiz.result'), ['q1_based_outside_eu' => true])->assertStatus(422);

    expect(QuizResult::count())->toBe(0);
});

it('shows quiz results in the back-office for an authenticated admin', function () {
    QuizResult::factory()->create([
        'q3_sells_restricted' => true,
        'outcome' => QuizOutcome::Excluded,
    ]);

    actingAs(User::factory()->create());

    get(route('admin.quiz.index'))
        ->assertOk()
        ->assertSee('Catégorie exclue');
});

it('redirects guests from the quiz back-office to the login', function () {
    get(route('admin.quiz.index'))->assertRedirect(route('admin.login'));
});
