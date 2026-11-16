<?php

declare(strict_types=1);

use App\Enums\Quiz\QuizOutcome;
use App\Livewire\Admin\QuizResultList;
use App\Livewire\Admin\SubmissionList;
use App\Models\QuizResult;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 | Listes du back-office anterieures au blog (dossiers, prises de contact, quiz) : une lecture qui echoue
 | laisse un message a la place de la liste, jamais une page d'erreur ; la liste du quiz ne lit la base
 | que par son service, en un nombre de requetes fixe.
 */

uses(RefreshDatabase::class);

beforeEach(fn () => actingAs(User::factory()->create()));

it('shows a message in place of the dossier list when it cannot be loaded', function () {
    Log::spy();
    Schema::rename('submissions', 'submissions_unavailable');

    Livewire::test(SubmissionList::class)
        ->assertOk()
        ->assertHasErrors('submissions-load-failed')
        ->assertSee('Le chargement des dossiers a échoué.')
        ->assertSee('Réessayer');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'admin submission list'));

    Schema::rename('submissions_unavailable', 'submissions');
});

it('names the contacts in the message when the contact list cannot be loaded', function () {
    Log::spy();
    Schema::rename('submissions', 'submissions_unavailable');

    get(route('admin.contacts.index'))
        ->assertOk()
        ->assertSee('Le chargement des prises de contact a échoué.');

    Schema::rename('submissions_unavailable', 'submissions');
});

it('shows the dossier list again once the reading works', function () {
    Log::spy();
    Submission::factory()->starter()->create(['company_name' => 'Atelier Durand']);
    Schema::rename('submissions', 'submissions_unavailable');

    $list = Livewire::test(SubmissionList::class)->assertHasErrors('submissions-load-failed');

    Schema::rename('submissions_unavailable', 'submissions');

    $list->call('$refresh')
        ->assertHasNoErrors()
        ->assertSee('Atelier Durand');
});

it('lists the quiz answers with the count of each outcome, in 3 queries', function () {
    QuizResult::factory()->count(3)->create(['outcome' => QuizOutcome::Concerned]);
    QuizResult::factory()->create(['outcome' => QuizOutcome::Excluded, 'q3_sells_restricted' => true]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $list = Livewire::test(QuizResultList::class)->assertOk();

    // Compteurs (une requete groupee), nombre de reponses, page.
    expect(count(DB::getQueryLog()))->toBe(3);
    DB::disableQueryLog();

    $list->assertViewHas('counts', ['concerned' => 3, 'excluded' => 1, 'not_concerned' => 0])
        ->assertViewHas('results', fn ($results): bool => $results->total() === 4)
        ->assertSee('Affichage de');
});

it('reads an empty quiz list in 2 queries', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(QuizResultList::class)
        ->assertOk()
        ->assertSee('Aucune réponse au quiz pour le moment.');

    expect(count(DB::getQueryLog()))->toBe(2);
    DB::disableQueryLog();
});

it('filters the quiz answers by outcome and ignores an outcome it does not know', function () {
    QuizResult::factory()->count(2)->create(['outcome' => QuizOutcome::Concerned]);
    QuizResult::factory()->create(['outcome' => QuizOutcome::NotConcerned]);

    Livewire::test(QuizResultList::class)
        ->set('outcome', QuizOutcome::NotConcerned->value)
        ->assertViewHas('results', fn ($results): bool => $results->total() === 1)
        // Les compteurs portent sur toutes les reponses, pas sur le filtre.
        ->assertViewHas('counts', ['concerned' => 2, 'excluded' => 0, 'not_concerned' => 1])
        // La bande des compteurs sert de filtre : la cellule choisie est marquee, plus celle de « Tous ».
        ->assertSeeHtml('aria-pressed="true" data-outcome="not_concerned"')
        ->assertSeeHtml('aria-pressed="false" data-outcome="all"')
        ->set('outcome', QuizOutcome::Excluded->value)
        ->assertSee('Aucune réponse ne correspond.')
        // Une valeur inconnue dans l'adresse : le filtre est ignore.
        ->set('outcome', 'unknown')
        ->assertViewHas('results', fn ($results): bool => $results->total() === 3);
});

it('shows a message in place of the quiz answers when they cannot be loaded', function () {
    Log::spy();
    Schema::rename('quiz_results', 'quiz_results_unavailable');

    Livewire::test(QuizResultList::class)
        ->assertOk()
        ->assertHasErrors('quiz-load-failed')
        ->assertSee('Le chargement des réponses au quiz a échoué.')
        ->assertDontSee('Concerné');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'admin quiz result list'));

    Schema::rename('quiz_results_unavailable', 'quiz_results');
});
