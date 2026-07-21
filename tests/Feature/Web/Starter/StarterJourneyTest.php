<?php

use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Enums\Contract\SignatureEventOutcome;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Signature\SignatureException;
use App\Livewire\Web\Funnel\StarterJourney;
use App\Models\Contract;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
    ])->submission;
}

/** Binds a stub signature provider that reports the given completion state (like SignWell would). */
function bindSignatureGateway(bool $signed): void
{
    app()->bind(SignatureGatewayInterface::class, fn () => new class($signed) implements SignatureGatewayInterface
    {
        public function __construct(private bool $signed) {}

        public function key(): string
        {
            return 'stub';
        }

        public function createSigningSession(Contract $contract): SigningSessionData
        {
            return new SigningSessionData('ref-1', 'https://example.com/sign');
        }

        public function currentSigningUrl(Contract $contract): ?string
        {
            return $this->signed ? null : 'https://example.com/sign';
        }

        public function checkStatus(Contract $contract): SignatureWebhookData
        {
            return new SignatureWebhookData('ref-1', $this->signed ? SignatureEventOutcome::Signed : SignatureEventOutcome::Unresolved);
        }

        public function parseWebhook(Request $request): SignatureWebhookData
        {
            return new SignatureWebhookData('ref-1', $this->signed ? SignatureEventOutcome::Signed : SignatureEventOutcome::Unresolved);
        }

        public function downloadSignedDocument(Contract $contract): ?string
        {
            return $this->signed ? 'contracts/ref-1.pdf' : null;
        }
    });
}

it('walks the STARTER journey end-to-end through the UI and the fake dev routes', function () {
    Storage::fake('local');
    $submission = openStarterDossier();
    $token = $submission->resume_token;

    // Step 1 - the journey page renders at the sign step.
    get(route('get-started.starter.journey', ['dossier' => $token]))
        ->assertOk()
        ->assertSeeLivewire(StarterJourney::class)
        ->assertSee('Sign your Responsible Person mandate');

    // Clicking "sign" captures the mandate details, starts the fake signing session and redirects.
    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->set('incorporationPlace', 'Toronto, Canada')
        ->set('foundingYear', '2015')
        ->set('activity', 'handmade ceramics')
        ->call('sign')
        ->assertRedirect(route('get-started.starter.dev-sign', ['dossier' => $token]));

    // The dev-sign route stands in for the provider webhook: contract signed -> awaiting documents.
    get(route('get-started.starter.dev-sign', ['dossier' => $token]))
        ->assertRedirect(route('get-started.starter.journey', ['dossier' => $token]));
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
        ->assertRedirect(route('get-started.starter.dev-pay', ['dossier' => $token]));

    expect($submission->fresh()->payments()->where('status', PaymentStatus::Pending)->count())->toBe(1);

    // The dev-pay route stands in for the provider webhook: payment succeeded -> paid, land on my file.
    get(route('get-started.starter.dev-pay', ['dossier' => $token]))
        ->assertRedirect(route('my-project', ['dossier' => $token]));
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);

    // The paid dossier now lives in its "my file" space (the journey redirects there).
    get(route('get-started.starter.journey', ['dossier' => $token]))
        ->assertRedirect(route('my-project', ['dossier' => $token]));
    get(route('my-project', ['dossier' => $token]))
        ->assertOk()
        ->assertSee('Your documents');
});

it('confirms the signature on the signer return and shows the success banner', function () {
    // The provider answers "signed", exactly like SignWell once the signer completed on its hosted page.
    bindSignatureGateway(signed: true);

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
    bindSignatureGateway(signed: false);

    $submission = openStarterDossier();
    $submission->contract->update(['signature_provider' => 'stub', 'signature_provider_reference' => 'ref-1']);

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('confirmSignature')
        ->assertHasErrors('journey');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::InProgress);
});

it('auto-confirms on resume a signature already completed at the provider (browser closed before return)', function () {
    // Silent self-heal: the provider has the signature but our DB never learned it (no webhook).
    bindSignatureGateway(signed: true);

    $submission = openStarterDossier();
    $submission->contract->update(['signature_provider' => 'stub', 'signature_provider_reference' => 'ref-1']);

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('autoConfirmSignature')
        ->assertHasNoErrors()
        ->assertSee('Upload your documents');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments);
});

it('reuses the in-flight signing session on resume instead of creating a second one', function () {
    // Fake driver: a session already exists (provider_reference set), the contract is not signed yet.
    $submission = openStarterDossier();
    $submission->contract->update(['signature_provider' => 'fake', 'signature_provider_reference' => 'fake_existing']);

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('sign')
        ->assertRedirect(route('get-started.starter.dev-sign', ['dossier' => $submission->resume_token]));

    // The reference was not overwritten: no second session/document was created.
    expect($submission->fresh()->contract->signature_provider_reference)->toBe('fake_existing');
});

it('requires the mandate details before starting the signature', function () {
    $submission = openStarterDossier();

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->call('sign')
        ->assertHasErrors(['incorporationPlace', 'foundingYear', 'activity']);

    expect($submission->fresh()->status)->toBe(SubmissionStatus::InProgress)
        ->and($submission->fresh()->contract->signature_provider_reference)->toBeNull();
});

it('saves the mandate details to the contract before signing', function () {
    $submission = openStarterDossier();

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->set('incorporationPlace', 'Toronto, Canada')
        ->set('foundingYear', '2015')
        ->set('activity', 'the design and sale of ceramics')
        ->call('sign')
        ->assertHasNoErrors()
        ->assertRedirect(route('get-started.starter.dev-sign', ['dossier' => $submission->resume_token]));

    expect($submission->fresh()->contract->filled_fields)->toMatchArray([
        'incorporation_place' => 'Toronto, Canada',
        'founding_year' => '2015',
        'activity' => 'the design and sale of ceramics',
    ]);
});

it('rejects a founding year that is not four digits', function () {
    $submission = openStarterDossier();

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->set('incorporationPlace', 'Toronto, Canada')
        ->set('foundingYear', '15')
        ->set('activity', 'ceramics')
        ->call('sign')
        ->assertHasErrors('foundingYear');
});

it('pre-fills the mandate fields from what was already saved (resume)', function () {
    $submission = openStarterDossier();
    $submission->contract->update(['filled_fields' => [
        'incorporation_place' => 'Berlin, Germany',
        'founding_year' => '2019',
        'activity' => 'toys',
    ]]);

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->assertSet('incorporationPlace', 'Berlin, Germany')
        ->assertSet('foundingYear', '2019')
        ->assertSet('activity', 'toys');
});

it('shows the first year prorated to the signature date on the payment step', function () {
    $submission = openStarterDossier();
    $submission->update(['status' => SubmissionStatus::AwaitingPayment]);
    $submission->contract->update(['signature_status' => SignatureStatus::Signed, 'signed_at' => Carbon::create(2026, 7, 15)]);

    // July -> 6 remaining months -> 333 * 6/12 = 166.50, with the full-fee note.
    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->assertSee('€166.50')
        ->assertSee('invoiced each January');
});

it('charges the first year prorated from the signature date', function () {
    $submission = openStarterDossier();
    $submission->update(['status' => SubmissionStatus::AwaitingPayment]);
    $submission->contract->update(['signature_status' => SignatureStatus::Signed, 'signed_at' => Carbon::create(2026, 7, 15)]);
    foreach (['turnover_proof', 'technical_documentation'] as $type) {
        $submission->uploadedDocuments()->create([
            'type' => $type,
            'file_path' => "contracts/{$type}.pdf",
            'original_filename' => "{$type}.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1000,
            'uploaded_at' => now(),
        ]);
    }

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('pay')
        ->assertHasNoErrors();

    expect($submission->fresh()->payments()->latest()->first()->amount_cents)->toBe(16650);
});

/** A dossier at the payment step with a pending Stripe checkout in flight. */
function dossierAwaitingStripePayment(): Submission
{
    $submission = openStarterDossier();
    $submission->update(['status' => SubmissionStatus::AwaitingPayment]);
    $submission->contract->update(['signature_status' => SignatureStatus::Signed]);
    $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Pending,
    ]);

    return $submission->fresh();
}

it('auto-confirms the payment on return and redirects to the my-project space', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'payment_status' => 'paid'])]);

    $submission = dossierAwaitingStripePayment();

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->call('pollPayment')
        ->assertRedirect(route('my-project', ['dossier' => $submission->resume_token]));

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
});

it('keeps confirming silently (no error) while an async payment is still pending', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'payment_status' => 'unpaid'])]);

    $submission = dossierAwaitingStripePayment();

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->call('pollPayment')
        ->assertHasNoErrors()
        ->assertSet('paymentChecks', 1);

    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingPayment);
});

it('shows the confirming state (not a second Pay button) while a payment is in flight', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);

    $submission = dossierAwaitingStripePayment();

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->assertSee('confirming your payment')
        ->assertDontSee('securely'); // le bouton "Pay ... securely" n'est plus propose
});

it('reuses the in-flight checkout on resume instead of creating a second charge', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    Http::fake(['*/v1/checkout/sessions/*' => Http::response([
        'id' => 'cs_1',
        'status' => 'open',
        'url' => 'https://checkout.stripe.com/c/pay/cs_1',
    ])]);

    $submission = dossierAwaitingStripePayment();

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->call('pay')
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_1');

    // Un seul paiement : aucune nouvelle session/charge creee.
    expect($submission->fresh()->payments()->count())->toBe(1);
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

    get(route('get-started.starter.journey', ['dossier' => $submission->resume_token]))
        ->assertNotFound();
});

it('returns 404 when the token belongs to a non-STARTER submission', function () {
    Submission::factory()->create([
        'type' => SubmissionType::Contact,
        'resume_token' => 'contact-token-xyz',
        'resume_expires_at' => now()->addDay(),
    ]);

    get(route('get-started.starter.journey', ['dossier' => 'contact-token-xyz']))
        ->assertNotFound();
});

it('blocks the fake dev completion routes in production', function () {
    $submission = openStarterDossier();
    app()->instance('env', 'production');

    get(route('get-started.starter.dev-sign', ['dossier' => $submission->resume_token]))
        ->assertNotFound();
    get(route('get-started.starter.dev-pay', ['dossier' => $submission->resume_token]))
        ->assertNotFound();
});

it('shows a graceful error and does not crash when the signature provider fails', function () {
    $submission = openStarterDossier();

    app()->bind(SignatureGatewayInterface::class, fn () => new class implements SignatureGatewayInterface
    {
        public function key(): string
        {
            return 'stub';
        }

        public function createSigningSession(Contract $contract): SigningSessionData
        {
            throw SignatureException::apiRequestFailed('create document');
        }

        public function currentSigningUrl(Contract $contract): ?string
        {
            return null;
        }

        public function checkStatus(Contract $contract): SignatureWebhookData
        {
            return new SignatureWebhookData('x', SignatureEventOutcome::Unresolved);
        }

        public function parseWebhook(Request $request): SignatureWebhookData
        {
            return new SignatureWebhookData('x', SignatureEventOutcome::Unresolved);
        }

        public function downloadSignedDocument(Contract $contract): ?string
        {
            return null;
        }
    });

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->set('incorporationPlace', 'Toronto, Canada')
        ->set('foundingYear', '2015')
        ->set('activity', 'handmade ceramics')
        ->call('sign')
        ->assertHasErrors('journey');

    // Le dossier reste au stade signature : aucune transition cassee, pas de crash.
    expect($submission->fresh()->status)->toBe(SubmissionStatus::InProgress);
});

it('builds a journey URL from the submission model using the resume token as route key', function () {
    // Certains appels route() recoivent le modele resolu par le binding (route->parameters()), pas le
    // token brut. La cle de route du dossier doit donc etre le resume_token, sinon route() genererait
    // l'id et l'URL ne resoudrait pas (404).
    $submission = openStarterDossier();

    $url = route('get-started.starter.journey', ['dossier' => $submission]);

    expect($url)->toEndWith('/get-started/starter/'.$submission->resume_token);

    get($url)->assertOk();
});

it('reviews a completed step read-only and returns, and locks forward navigation', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);
    Contract::factory()->for($submission)->signed()->create();

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->assertSet('viewStep', null)
        ->call('goToStep', 'sign')->assertSet('viewStep', 'sign')            // revoir une etape terminee
        ->call('goToStep', 'documents')->assertSet('viewStep', 'documents')
        ->call('goToStep', 'payment')->assertSet('viewStep', null)           // retour a l'etape en cours
        ->call('goToStep', 'nope')->assertSet('viewStep', null);             // cible invalide -> ignoree
});

it('locks review navigation to a not-yet-reached step', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::InProgress]); // etape signature

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->call('goToStep', 'payment')->assertSet('viewStep', null);         // etape future -> ignoree
});
