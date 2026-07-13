<?php

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Web\Contact\ContactForm;
use App\Mail\ContactSubmissionReceived;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates a contact submission and sends a notification on valid input', function () {
    Mail::fake();

    Livewire::test(ContactForm::class)
        ->set('name', 'Maya Thornton')
        ->set('email', 'maya@example.com')
        ->set('website_url', 'https://wildthread.example')
        ->set('message', 'We ship handmade ceramics to the EU and need help.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    $submission = Submission::sole();

    expect($submission->type)->toBe(SubmissionType::Contact)
        ->and($submission->status)->toBe(SubmissionStatus::New)
        ->and($submission->first_name)->toBe('Maya Thornton')
        ->and($submission->email)->toBe('maya@example.com')
        ->and($submission->website_url)->toBe('https://wildthread.example')
        ->and($submission->reference)->not->toBeEmpty();

    Mail::assertSent(ContactSubmissionReceived::class, function (ContactSubmissionReceived $mail) {
        return $mail->hasTo(config('festilaw.notification_email'));
    });
});

it('requires name, email and message', function () {
    Livewire::test(ContactForm::class)
        ->call('save')
        ->assertHasErrors(['name', 'email', 'message']);

    expect(Submission::count())->toBe(0);
});

it('rejects an invalid email', function () {
    Livewire::test(ContactForm::class)
        ->set('name', 'Test')
        ->set('email', 'not-an-email')
        ->set('message', 'Hello there.')
        ->call('save')
        ->assertHasErrors(['email']);

    expect(Submission::count())->toBe(0);
});
