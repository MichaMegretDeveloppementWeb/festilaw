<?php

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Admin\LoginForm;
use App\Livewire\Admin\SubmissionDetail;
use App\Livewire\Admin\SubmissionList;
use App\Mail\StarterResponsiblePersonIssued;
use App\Mail\StarterResumeLink;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('redirects guests from the back-office to the login', function () {
    get(route('admin.submissions.index'))->assertRedirect(route('admin.login'));
    get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

it('rejects invalid admin credentials', function () {
    User::factory()->create(['email' => 'admin@festilaw.com', 'password' => 'good-password']);

    Livewire::test(LoginForm::class)
        ->set('email', 'admin@festilaw.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('logs an admin in with valid credentials', function () {
    User::factory()->create(['email' => 'admin@festilaw.com', 'password' => 'good-password']);

    Livewire::test(LoginForm::class)
        ->set('email', 'admin@festilaw.com')
        ->set('password', 'good-password')
        ->call('login')
        ->assertRedirect(route('admin.submissions.index'));

    expect(auth()->check())->toBeTrue();
});

it('lists and filters submissions for an authenticated admin', function () {
    $starter = Submission::factory()->starter()->create(['email' => 'buyer@example.com']);
    $contact = Submission::factory()->create(['type' => SubmissionType::Contact, 'email' => 'hello@example.com']);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionList::class)
        ->assertSee($starter->reference)
        ->assertSee($contact->reference)
        ->set('search', 'buyer@example.com')
        ->assertSee($starter->reference)
        ->assertDontSee($contact->reference);
});

it('opens the detail when a list row is clicked', function () {
    $submission = Submission::factory()->starter()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionList::class)
        ->call('show', $submission->id)
        ->assertRedirect(route('admin.submissions.show', ['submission' => $submission->id]));
});

it('changes a submission status from the detail screen', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('newStatus', SubmissionStatus::Paid->value)
        ->call('updateStatus')
        ->assertHasNoErrors();

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
});

it('adds an internal note attributed to the current admin', function () {
    $submission = Submission::factory()->starter()->create();
    $admin = User::factory()->create();

    actingAs($admin);

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('noteBody', 'Rappeler le client demain matin.')
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSet('noteBody', '');

    expect($submission->notes()->count())->toBe(1);
    expect($submission->notes()->first())
        ->body->toBe('Rappeler le client demain matin.')
        ->author_id->toBe($admin->id);
});

it('resends the resume link to a starter client', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->call('resendLink');

    Mail::assertSent(StarterResumeLink::class, fn ($mail) => $mail->hasTo($submission->email));
});

it('issues the EU responsible person address, completes the dossier and emails the client', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('rpAddress', "Festilaw SAS\n1 rue de l'Europe, 75001 Paris")
        ->call('issueResponsiblePerson')
        ->assertHasNoErrors();

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::Completed);
    expect($submission->eu_rp_address)->toContain('rue de l');

    Mail::assertSent(StarterResponsiblePersonIssued::class, fn ($mail) => $mail->hasTo($submission->email));
});

it('deletes a dossier and redirects to the list', function () {
    $submission = Submission::factory()->starter()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->call('deleteDossier')
        ->assertRedirect(route('admin.submissions.index'));

    expect(Submission::find($submission->id))->toBeNull();
});
