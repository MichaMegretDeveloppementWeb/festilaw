<?php

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Admin\LoginForm;
use App\Livewire\Admin\SubmissionDetail;
use App\Livewire\Admin\SubmissionList;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('changes a submission status from the detail screen', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('newStatus', SubmissionStatus::Paid->value)
        ->call('updateStatus')
        ->assertHasNoErrors();

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
});
