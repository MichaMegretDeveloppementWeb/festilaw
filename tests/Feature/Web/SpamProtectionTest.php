<?php

use App\Livewire\Web\Contact\ContactForm;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('silently drops a submission when the honeypot is filled, creating nothing', function () {
    Livewire::test(ContactForm::class)
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('message', 'buy cheap stuff now')
        ->set('hp', 'i-am-a-bot')
        ->call('save')
        ->assertSet('sent', true);

    expect(Submission::count())->toBe(0);
    Mail::assertNothingSent();
});

it('throttles the form after too many attempts from the same IP', function () {
    $fill = fn () => Livewire::test(ContactForm::class)
        ->set('name', 'Real Person')
        ->set('email', 'real@example.com')
        ->set('message', 'A genuine enquiry about GPSR compliance.');

    // 5 tentatives autorisees.
    for ($i = 0; $i < 5; $i++) {
        $fill()->call('save')->assertSet('sent', true);
    }

    // La 6e dans la fenetre est bloquee : pas d'envoi, message d'erreur.
    $fill()->call('save')
        ->assertSet('sent', false)
        ->assertHasErrors('form');
});
