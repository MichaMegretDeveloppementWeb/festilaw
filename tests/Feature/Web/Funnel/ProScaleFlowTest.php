<?php

use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('opens a PRO file into the same self-service journey as Creator', function () {
    Mail::fake();

    $outcome = app(CreateStarterSubmissionAction::class)->execute([
        'company_name' => 'Acme Goods',
        'first_name' => 'Dana',
        'email' => 'acme@example.com',
    ], SubmissionType::Pro);

    expect($outcome->isNew)->toBeTrue()
        ->and($outcome->submission->type)->toBe(SubmissionType::Pro)
        ->and($outcome->submission->status)->toBe(SubmissionStatus::InProgress)
        ->and($outcome->submission->resume_token)->not->toBeNull()
        ->and($outcome->submission->contract)->not->toBeNull();
});

// Le parcours SCALE complet (creation + audit 75 EUR idempotent + URLs de retour Scale + confirmation
// serveur + prise de rendez-vous) est teste de bout en bout dans ScaleJourneyTest.
