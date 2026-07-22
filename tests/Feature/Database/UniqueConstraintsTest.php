<?php

use App\Enums\Appointment\AppointmentStatus;
use App\Enums\Document\DocumentType;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Models\Submission;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function documentRow(): array
{
    return [
        'type' => DocumentType::TurnoverProof,
        'file_path' => 'starter-documents/x/'.uniqid().'.pdf',
        'original_filename' => 'proof.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1234,
        'uploaded_at' => now(),
    ];
}

it('rejects two uploaded documents of the same type on one dossier', function () {
    $submission = Submission::factory()->starter()->create();
    $submission->uploadedDocuments()->create(documentRow());

    expect(fn () => $submission->uploadedDocuments()->create(documentRow()))
        ->toThrow(QueryException::class);
});

it('allows the same document type on two different dossiers', function () {
    Submission::factory()->starter()->create()->uploadedDocuments()->create(documentRow());
    $other = Submission::factory()->starter()->create();

    expect($other->uploadedDocuments()->create(documentRow())->exists)->toBeTrue();
});

it('rejects a second appointment on the same dossier', function () {
    $submission = Submission::factory()->starter()->create();
    $submission->appointment()->create(['status' => AppointmentStatus::Requested]);

    expect(fn () => $submission->appointment()->create(['status' => AppointmentStatus::Requested]))
        ->toThrow(QueryException::class);
});

it('rejects two payments sharing the same provider reference', function () {
    $submission = Submission::factory()->starter()->create();
    $row = [
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_dup',
        'status' => PaymentStatus::Pending,
    ];
    $submission->payments()->create($row);

    expect(fn () => $submission->payments()->create($row))
        ->toThrow(QueryException::class);
});

it('allows several pending payments with a null provider reference (NULLs stay distinct)', function () {
    $submission = Submission::factory()->starter()->create();
    $row = [
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => null,
        'status' => PaymentStatus::Pending,
    ];

    $submission->payments()->create($row);
    $submission->payments()->create($row);

    expect($submission->payments()->whereNull('provider_reference')->count())->toBe(2);
});
