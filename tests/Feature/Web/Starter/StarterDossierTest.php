<?php

use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/** A paid ("active") dossier with a signed mandate, one document and a succeeded payment. */
function activeStarterDossier(SubmissionType $type = SubmissionType::Starter, int $amountCents = 33300): Submission
{
    $state = $type === SubmissionType::Pro ? 'pro' : 'starter';
    $submission = Submission::factory()->{$state}()->create([
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
        'amount_cents' => $amountCents,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    return $submission->fresh();
}

it('shows the active my-project space with reference, renewal date and downloads', function () {
    $submission = activeStarterDossier();

    get(route('my-project', ['dossier' => 'mydossier']))
        ->assertOk()
        ->assertSee('Active')
        ->assertSee($submission->reference)
        ->assertSee('Creator Pack')
        ->assertSee('Next renewal')
        ->assertSee(now()->startOfYear()->addYear()->isoFormat('D MMMM YYYY')) // 1er janvier suivant
        ->assertSee('€333') // montant paye, affiche apres l'etape Payment
        ->assertSee(now()->isoFormat('D MMMM YYYY')) // date du paiement, dans les memes parentheses
        ->assertSee('Your documents')
        ->assertSee('Signed Responsible Person mandate')
        ->assertSee('Download')
        ->assertDontSee('Resume my project'); // rien a reprendre : c'est actif
});

it('shows the Pro pack label and annual price on an active Pro dossier', function () {
    activeStarterDossier(SubmissionType::Pro, 120000);

    get(route('my-project', ['dossier' => 'mydossier']))
        ->assertOk()
        ->assertSee('Active')
        ->assertSee('Pro Pack')
        ->assertSee('€1,200') // tarif annuel plein du pack Pro
        ->assertDontSee('Creator Pack');
});

it('sends a paid dossier from the journey to its my-project space', function () {
    activeStarterDossier();

    get(route('get-started.starter.journey', ['dossier' => 'mydossier']))
        ->assertRedirect(route('my-project', ['dossier' => 'mydossier']));
});

it('shows an in-progress project as a status page with a resume link, not a redirect', function () {
    Submission::factory()->starter()->create([
        'status' => SubmissionStatus::AwaitingPayment,
        'resume_token' => 'inprogress',
    ]);

    get(route('my-project', ['dossier' => 'inprogress']))
        ->assertOk()
        ->assertSee('In progress')
        ->assertSee('Resume my project')
        ->assertSee(route('get-started.starter.journey', ['dossier' => 'inprogress']), false);
});

it('downloads the signed mandate for the dossier', function () {
    Storage::fake('local');
    Storage::disk('local')->put('contracts/mandate.pdf', '%PDF-signed');
    $submission = activeStarterDossier();

    get(route('get-started.starter.mandate', ['dossier' => 'mydossier']))
        ->assertOk()
        ->assertDownload('festilaw-mandate-'.$submission->reference.'.pdf');
});

it('downloads an uploaded document for the dossier', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/turnover.pdf', '%PDF-doc');
    $document = activeStarterDossier()->uploadedDocuments()->first();

    get(route('get-started.starter.document', ['dossier' => 'mydossier', 'document' => $document->id]))
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

    get(route('get-started.starter.document', ['dossier' => 'mydossier', 'document' => $foreignDocument->id]))
        ->assertNotFound();
});

it('404s when the mandate file is missing on disk', function () {
    Storage::fake('local'); // rien depose
    activeStarterDossier();

    get(route('get-started.starter.mandate', ['dossier' => 'mydossier']))
        ->assertNotFound();
});
