<?php

use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Web\Funnel\StarterJourney;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['fake']);
    config()->set('signature.default', 'fake');
    config()->set('festilaw.starter.required_documents', ['turnover_proof', 'technical_documentation']);
    Mail::fake();
});

/** Opens a fresh STARTER dossier (submission + unsigned contract + resume token). */
function openStarterDossier(): Submission
{
    return app(CreateStarterSubmissionAction::class)->execute([
        'company_name' => 'Wildthread Ceramics',
        'first_name' => 'Maya',
        'email' => 'maya@example.com',
    ]);
}

it('walks the STARTER journey end-to-end through the UI and the fake dev routes', function () {
    Storage::fake('local');
    $submission = openStarterDossier();
    $token = $submission->resume_token;

    // Step 1 - the journey page renders at the sign step.
    get(route('get-started.starter.journey', ['locale' => 'en', 'dossier' => $token]))
        ->assertOk()
        ->assertSeeLivewire(StarterJourney::class)
        ->assertSee('Sign your Responsible Person mandate');

    // Clicking "sign" starts the fake signing session and redirects to the dev-sign route.
    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->call('sign')
        ->assertRedirect(route('get-started.starter.dev-sign', ['locale' => 'en', 'dossier' => $token]));

    // The dev-sign route stands in for the provider webhook: contract signed -> awaiting documents.
    get(route('get-started.starter.dev-sign', ['locale' => 'en', 'dossier' => $token]))
        ->assertRedirect(route('get-started.starter.journey', ['locale' => 'en', 'dossier' => $token]));
    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments);

    // Step 2 - drop both required documents and submit in one go; the dossier then becomes awaiting payment.
    $component = Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->set('documents.turnover_proof', UploadedFile::fake()->create('turnover.pdf', 120, 'application/pdf'))
        ->set('documents.technical_documentation', UploadedFile::fake()->create('tech.pdf', 120, 'application/pdf'))
        ->call('submitDocuments')
        ->assertHasNoErrors();

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::AwaitingPayment)
        ->and($submission->uploadedDocuments)->toHaveCount(2);

    foreach ($submission->uploadedDocuments as $document) {
        Storage::disk('local')->assertExists($document->file_path);
    }

    // Step 3 - pay: creates a pending payment and redirects to the dev-pay route.
    $component->call('pay')
        ->assertRedirect(route('get-started.starter.dev-pay', ['locale' => 'en', 'dossier' => $token]));

    expect($submission->fresh()->payments()->where('status', PaymentStatus::Pending)->count())->toBe(1);

    // The dev-pay route stands in for the provider webhook: payment succeeded -> paid.
    get(route('get-started.starter.dev-pay', ['locale' => 'en', 'dossier' => $token]))
        ->assertRedirect(route('get-started.starter.journey', ['locale' => 'en', 'dossier' => $token]));
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);

    // The journey now shows the completed state.
    get(route('get-started.starter.journey', ['locale' => 'en', 'dossier' => $token]))
        ->assertOk()
        ->assertSee('Your Creator Pack is active.');
});

it('confirms the signature on the signer return and shows the success banner', function () {
    // A provider that answers "signed" to the return poll, exactly like SignWell once the signer
    // completed on its hosted page (no webhook involved).
    app()->bind(SignatureGatewayInterface::class, fn () => new class implements SignatureGatewayInterface
    {
        public function key(): string
        {
            return 'stub';
        }

        public function createSigningSession(Contract $contract): SigningSessionData
        {
            return new SigningSessionData('ref-1', 'https://example.com/sign');
        }

        public function checkStatus(Contract $contract): SignatureWebhookData
        {
            return new SignatureWebhookData('ref-1', true, 'contracts/ref-1.pdf');
        }

        public function parseWebhook(Request $request): SignatureWebhookData
        {
            return new SignatureWebhookData('ref-1', true, null);
        }
    });

    $submission = openStarterDossier();
    $submission->contract->update(['signature_provider' => 'stub', 'signature_provider_reference' => 'ref-1']);

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('confirmSignature')
        ->assertHasNoErrors()
        ->assertSee('Mandate signed')
        ->assertSee('Upload your documents');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments)
        ->and($submission->fresh()->contract->signature_status)->toBe(SignatureStatus::Signed);
});

it('tells the signer to retry when the signature is not recorded yet on return', function () {
    // The provider still reports "pending" (e.g. the signer returned a hair too early).
    app()->bind(SignatureGatewayInterface::class, fn () => new class implements SignatureGatewayInterface
    {
        public function key(): string
        {
            return 'stub';
        }

        public function createSigningSession(Contract $contract): SigningSessionData
        {
            return new SigningSessionData('ref-1', 'https://example.com/sign');
        }

        public function checkStatus(Contract $contract): SignatureWebhookData
        {
            return new SignatureWebhookData('ref-1', false, null);
        }

        public function parseWebhook(Request $request): SignatureWebhookData
        {
            return new SignatureWebhookData('ref-1', false, null);
        }
    });

    $submission = openStarterDossier();
    $submission->contract->update(['signature_provider' => 'stub', 'signature_provider_reference' => 'ref-1']);

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('confirmSignature')
        ->assertHasErrors('journey');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::InProgress);
});

it('rejects an oversized document on submit', function () {
    Storage::fake('local');
    $submission = openStarterDossier();
    $submission->update(['status' => SubmissionStatus::AwaitingDocuments]);
    $submission->contract->update(['signature_status' => SignatureStatus::Signed]);

    // Les deux documents sont deposes (presence OK) mais l'un depasse notre regle max:10240 (10 MB).
    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->set('documents.turnover_proof', UploadedFile::fake()->create('too-big.pdf', 11000, 'application/pdf'))
        ->set('documents.technical_documentation', UploadedFile::fake()->create('ok.pdf', 100, 'application/pdf'))
        ->call('submitDocuments')
        ->assertHasErrors(['documents.turnover_proof']);

    expect($submission->fresh()->uploadedDocuments)->toHaveCount(0);
});

it('shows an error when a required document is missing on submit', function () {
    Storage::fake('local');
    $submission = openStarterDossier();
    $submission->update(['status' => SubmissionStatus::AwaitingDocuments]);
    $submission->contract->update(['signature_status' => SignatureStatus::Signed]);

    // Un seul des deux documents requis : l'erreur s'affiche SOUS le document manquant, pas en global.
    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->set('documents.turnover_proof', UploadedFile::fake()->create('turnover.pdf', 100, 'application/pdf'))
        ->call('submitDocuments')
        ->assertHasErrors('documents.technical_documentation')
        ->assertHasNoErrors('documents.turnover_proof');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments)
        ->and($submission->fresh()->uploadedDocuments)->toHaveCount(0);
});

it('returns 404 for an expired resume token', function () {
    $submission = openStarterDossier();
    $submission->update(['resume_expires_at' => now()->subDay()]);

    get(route('get-started.starter.journey', ['locale' => 'en', 'dossier' => $submission->resume_token]))
        ->assertNotFound();
});

it('returns 404 when the token belongs to a non-STARTER submission', function () {
    Submission::factory()->create([
        'type' => SubmissionType::Contact,
        'resume_token' => 'contact-token-xyz',
        'resume_expires_at' => now()->addDay(),
    ]);

    get(route('get-started.starter.journey', ['locale' => 'en', 'dossier' => 'contact-token-xyz']))
        ->assertNotFound();
});

it('blocks the fake dev completion routes in production', function () {
    $submission = openStarterDossier();
    app()->instance('env', 'production');

    get(route('get-started.starter.dev-sign', ['locale' => 'en', 'dossier' => $submission->resume_token]))
        ->assertNotFound();
    get(route('get-started.starter.dev-pay', ['locale' => 'en', 'dossier' => $submission->resume_token]))
        ->assertNotFound();
});
