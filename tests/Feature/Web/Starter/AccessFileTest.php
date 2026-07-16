<?php

use App\Enums\Submission\SubmissionStatus;
use App\Livewire\Web\Funnel\AccessFileForm;
use App\Mail\StarterResumeLink;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('renders the access-my-project page', function () {
    get(route('find-my-project'))
        ->assertOk()
        ->assertSee('Access')
        ->assertSeeLivewire(AccessFileForm::class);
});

it('emails the file link when a dossier exists for the email', function () {
    Submission::factory()->starter()->create([
        'email' => 'buyer@example.com',
        'status' => SubmissionStatus::Paid,
        'resume_expires_at' => null,
    ]);

    Livewire::test(AccessFileForm::class)
        ->set('email', 'buyer@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    Mail::assertSent(StarterResumeLink::class, fn (StarterResumeLink $m) => $m->hasTo('buyer@example.com'));
});

it('shows the same message and sends nothing when no dossier exists (no leak)', function () {
    Livewire::test(AccessFileForm::class)
        ->set('email', 'nobody@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    Mail::assertNothingSent();
});

it('validates the email before sending', function () {
    Livewire::test(AccessFileForm::class)
        ->set('email', 'not-an-email')
        ->call('submit')
        ->assertHasErrors('email');

    Mail::assertNothingSent();
});
