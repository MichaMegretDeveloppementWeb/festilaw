<?php

use App\Enums\Submission\SubmissionType;
use App\Livewire\Web\Funnel\ScaleForm;
use App\Livewire\Web\Funnel\StarterForm;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('serves the get-started hub and the three parcours pages (noindex)', function () {
    get(route('get-started.index'))
        ->assertOk()
        ->assertSee('Choose your')
        ->assertSee('noindex, nofollow', false);

    get(route('get-started.starter'))->assertOk()->assertSeeLivewire(StarterForm::class);
    get(route('get-started.pro'))->assertOk()->assertSeeLivewire(StarterForm::class);
    get(route('get-started.scale'))->assertOk()->assertSeeLivewire(ScaleForm::class);
});

it('opens a STARTER file and redirects into the journey', function () {
    Mail::fake();

    $component = Livewire::test(StarterForm::class)
        ->set('company_name', 'Wildthread')
        ->set('first_name', 'Maya')
        ->set('email', 'maya@example.com')
        ->call('submit')
        ->assertHasNoErrors();

    $submission = Submission::where('type', SubmissionType::Starter)->sole();

    expect($submission->resume_token)->not->toBeNull();
    $component->assertRedirect(route('get-started.starter.journey', ['dossier' => $submission->resume_token]));
});

it('opens a PRO file through the same journey (self-service)', function () {
    Mail::fake();

    $component = Livewire::test(StarterForm::class, ['type' => 'pro'])
        ->set('company_name', 'Acme Goods')
        ->set('first_name', 'Dana')
        ->set('email', 'acme@example.com')
        ->call('submit')
        ->assertHasNoErrors();

    $submission = Submission::where('type', SubmissionType::Pro)->sole();

    expect($submission->resume_token)->not->toBeNull()
        ->and($submission->contract)->not->toBeNull(); // meme coquille de contrat que Creator
    $component->assertRedirect(route('get-started.starter.journey', ['dossier' => $submission->resume_token]));
});

it('requests a SCALE audit from the form', function () {
    Mail::fake();

    Livewire::test(ScaleForm::class)
        ->set('company_name', 'Bigco')
        ->set('email', 'bigco@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    expect(Submission::where('type', SubmissionType::Scale)->count())->toBe(1);
});

it('validates the required fields on the funnel forms', function () {
    Livewire::test(ScaleForm::class)->call('submit')->assertHasErrors(['company_name', 'email']);
    Livewire::test(StarterForm::class)->call('submit')->assertHasErrors(['company_name', 'first_name', 'email']);
    Livewire::test(StarterForm::class, ['type' => 'pro'])->call('submit')->assertHasErrors(['company_name', 'first_name', 'email']);
});
