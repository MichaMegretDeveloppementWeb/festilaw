<?php

use App\Enums\Document\DocumentType;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Appointment;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\QuizResult;
use App\Models\Submission;
use App\Models\UploadedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a UUID reference and casts enums on a submission', function () {
    $submission = Submission::factory()->starter()->create();

    expect($submission->reference)->not->toBeEmpty()
        ->and($submission->type)->toBe(SubmissionType::Starter)
        ->and($submission->status)->toBe(SubmissionStatus::InProgress);
});

it('wires every submission relation with correct casts', function () {
    $submission = Submission::factory()->starter()->create();

    QuizResult::factory()->for($submission)->create();
    Contract::factory()->for($submission)->signed()->create();
    // Une piece par type (contrainte d'unicite (submission_id, type)).
    UploadedDocument::factory()->for($submission)->count(2)->sequence(
        ['type' => DocumentType::TurnoverProof],
        ['type' => DocumentType::TechnicalDocumentation],
    )->create();
    Payment::factory()->for($submission)->succeeded()->create();
    Appointment::factory()->for($submission)->create();

    $submission->refresh();

    expect($submission->quizResult)->toBeInstanceOf(QuizResult::class)
        ->and($submission->contract)->toBeInstanceOf(Contract::class)
        ->and($submission->uploadedDocuments)->toHaveCount(2)
        ->and($submission->payments)->toHaveCount(1)
        ->and($submission->appointment)->toBeInstanceOf(Appointment::class);

    $payment = $submission->payments->first();

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->amount_cents)->toBeInt()
        ->and($submission->contract->filled_fields)->toBeArray();
});
