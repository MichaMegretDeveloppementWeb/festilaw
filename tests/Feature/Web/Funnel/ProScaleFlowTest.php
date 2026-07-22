<?php

use App\Actions\Web\Scale\CreateScaleSubmissionAction;
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

it('opens a SCALE audit request', function () {
    Mail::fake();

    $submission = app(CreateScaleSubmissionAction::class)->execute([
        'company_name' => 'Bigco',
        'email' => 'bigco@example.com',
    ]);

    expect($submission->type)->toBe(SubmissionType::Scale)
        ->and($submission->status)->toBe(SubmissionStatus::New);

    // Le paiement de l'audit (75 EUR) et la prise de rendez-vous sont construits et testes de bout en
    // bout au chantier SCALE (checkout idempotent + URLs de retour propres a Scale + garde du RDV).
});
