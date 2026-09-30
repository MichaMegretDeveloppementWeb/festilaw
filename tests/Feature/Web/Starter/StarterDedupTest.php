<?php

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Web\Funnel\StarterForm;
use App\Mail\StarterResumeLink;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function submitStarterForm(string $email)
{
    return Livewire::test(StarterForm::class)
        ->set('company_name', 'Wildthread')
        ->set('first_name', 'Maya')
        ->set('last_name', 'Thornton')
        ->set('email', $email)
        ->call('submit');
}

it('emails the resume link when a new dossier is opened', function () {
    submitStarterForm('maya@example.com')->assertHasNoErrors();

    expect(Submission::where('type', SubmissionType::Starter)->count())->toBe(1);
    Mail::assertSent(StarterResumeLink::class, fn (StarterResumeLink $m) => $m->hasTo('maya@example.com'));
});

it('does not open a second dossier for the same email and re-sends the link instead', function () {
    submitStarterForm('maya@example.com'); // premier dossier (redirige)

    Mail::fake(); // on isole le second envoi

    submitStarterForm('maya@example.com')
        ->assertHasNoErrors()
        ->assertSet('resent', true)
        ->assertNoRedirect()
        ->assertSee('You already have a Creator Pack application in progress');

    expect(Submission::where('type', SubmissionType::Starter)->count())->toBe(1);
    // L'e-mail rappelle que le pack se change depuis le dossier.
    Mail::assertSent(StarterResumeLink::class, fn (StarterResumeLink $m) => $m->hasTo('maya@example.com')
        && str_contains($m->render(), 'You can switch between the Creator Pack and the Pro Pack from your file'));
});

it('never changes the pack of an existing dossier from the form, and explains how to switch', function () {
    submitStarterForm('maya@example.com'); // dossier Creator ouvert

    // Le meme e-mail sur le formulaire Pro : pas de nouveau dossier, pas de changement de pack.
    Livewire::test(StarterForm::class, ['type' => 'pro'])
        ->set('company_name', 'Wildthread')
        ->set('first_name', 'Maya')
        ->set('last_name', 'Thornton')
        ->set('email', 'maya@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('resent', true)
        ->assertSee('You already have a Creator Pack application in progress')
        ->assertSee('To switch to the Pro Pack, open your file from that link and change your plan there, before paying.');

    expect(Submission::count())->toBe(1)
        ->and(Submission::sole()->type)->toBe(SubmissionType::Starter);
});

it('directs an already-active customer to their file instead of a new dossier', function () {
    Submission::factory()->starter()->paid()->create([
        'email' => 'maya@example.com',
    ]);

    submitStarterForm('maya@example.com')
        ->assertHasNoErrors()
        ->assertSet('resent', true)
        ->assertSet('resentActive', true)
        ->assertNoRedirect();

    // Aucun nouveau dossier : on renvoie le lien de son dossier actif.
    expect(Submission::where('type', SubmissionType::Starter)->count())->toBe(1);
    Mail::assertSent(StarterResumeLink::class, fn (StarterResumeLink $m) => $m->hasTo('maya@example.com'));
});

it('allows a new dossier when the open one for this email has expired', function () {
    Submission::factory()->starter()->create([
        'email' => 'maya@example.com',
        'status' => SubmissionStatus::InProgress,
        'resume_expires_at' => now()->subDay(),
    ]);

    submitStarterForm('maya@example.com')->assertHasNoErrors();

    expect(Submission::where('type', SubmissionType::Starter)->count())->toBe(2);
});
