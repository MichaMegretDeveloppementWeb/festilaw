<?php

use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/** A paid ("active") dossier with a signed mandate, one document and a succeeded payment. */
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
    $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    return $submission->fresh();
}

it('shows the my-file space with reference, renewal date and downloads', function () {
    $submission = activeStarterDossier();

    get(route('my-file', ['locale' => 'en', 'dossier' => 'mydossier']))
        ->assertOk()
        ->assertSee('Creator Pack')
        ->assertSee($submission->reference)
        ->assertSee('Next renewal')
        ->assertSee(now()->addYear()->isoFormat('D MMMM YYYY'))
        ->assertSee('Your documents')
        ->assertSee('Signed Responsible Person mandate')
        ->assertSee('Download');
});

it('sends a paid dossier from the journey to its my-file space', function () {
    activeStarterDossier();

    get(route('get-started.starter.journey', ['locale' => 'en', 'dossier' => 'mydossier']))
        ->assertRedirect(route('my-file', ['locale' => 'en', 'dossier' => 'mydossier']));
});

it('sends an unpaid dossier from my-file back to the journey', function () {
    $submission = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::AwaitingPayment,
        'resume_token' => 'unpaid',
    ]);

    get(route('my-file', ['locale' => 'en', 'dossier' => 'unpaid']))
        ->assertRedirect(route('get-started.starter.journey', ['locale' => 'en', 'dossier' => 'unpaid']));
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
