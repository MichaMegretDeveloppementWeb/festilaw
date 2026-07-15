<?php

use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/** A paid ("active") dossier with a signed mandate and one uploaded document. */
function activeStarterDossier(): Submission
{
    $submission = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::Paid,
        'resume_token' => 'mydossier',
        'resume_expires_at' => null,
        'locale' => 'en',
    ]);
    $submission->contract()->create([
        'signature_status' => SignatureStatus::Signed,
        'signed_file_path' => 'contracts/mandate.pdf',
        'filled_fields' => [],
    ]);
    $submission->uploadedDocuments()->create([
        'type' => 'turnover_proof',
        'file_path' => 'documents/turnover.pdf',
        'original_filename' => 'turnover.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1000,
        'uploaded_at' => now(),
    ]);

    return $submission->fresh();
}

it('shows the my-file space with downloads on a paid dossier', function () {
    activeStarterDossier();

    get(route('get-started.starter.journey', ['locale' => 'en', 'dossier' => 'mydossier']))
        ->assertOk()
        ->assertSee('Your Creator Pack is active.')
        ->assertSee('Your documents')
        ->assertSee('Signed Responsible Person mandate')
        ->assertSee('Download');
});

it('downloads the signed mandate for the dossier', function () {
    Storage::fake('local');
    Storage::disk('local')->put('contracts/mandate.pdf', '%PDF-signed');
    $submission = activeStarterDossier();

    get(route('get-started.starter.mandate', ['locale' => 'en', 'dossier' => 'mydossier']))
        ->assertOk()
        ->assertDownload('festilaw-mandate-'.$submission->reference.'.pdf');
});

it('downloads an uploaded document for the dossier', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/turnover.pdf', '%PDF-doc');
    $document = activeStarterDossier()->uploadedDocuments()->first();

    get(route('get-started.starter.document', ['locale' => 'en', 'dossier' => 'mydossier', 'document' => $document->id]))
        ->assertOk()
        ->assertDownload('turnover.pdf');
});

it('does not let a dossier link download another dossier document', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/foreign.pdf', '%PDF');
    activeStarterDossier();

    $other = Submission::factory()->starter()->create(['resume_token' => 'other', 'resume_expires_at' => null]);
    $foreignDocument = $other->uploadedDocuments()->create([
        'type' => 'turnover_proof',
        'file_path' => 'documents/foreign.pdf',
        'original_filename' => 'foreign.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 500,
        'uploaded_at' => now(),
    ]);

    get(route('get-started.starter.document', ['locale' => 'en', 'dossier' => 'mydossier', 'document' => $foreignDocument->id]))
        ->assertNotFound();
});

it('404s when the mandate file is missing on disk', function () {
    Storage::fake('local'); // rien depose
    activeStarterDossier();

    get(route('get-started.starter.mandate', ['locale' => 'en', 'dossier' => 'mydossier']))
        ->assertNotFound();
});
