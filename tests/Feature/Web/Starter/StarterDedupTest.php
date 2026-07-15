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
        ->assertNoRedirect();

    expect(Submission::where('type', SubmissionType::Starter)->count())->toBe(1);
    Mail::assertSent(StarterResumeLink::class, fn (StarterResumeLink $m) => $m->hasTo('maya@example.com'));
});

it('allows a new dossier when the previous one for this email is already finished', function () {
    Submission::factory()->starter()->create([
        'email' => 'maya@example.com',
        'status' => SubmissionStatus::Paid,
        'resume_expires_at' => now()->addDays(30),
    ]);

    submitStarterForm('maya@example.com')->assertHasNoErrors();

    expect(Submission::where('type', SubmissionType::Starter)->count())->toBe(2);
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
