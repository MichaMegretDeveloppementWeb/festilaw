<?php

use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Actions\Web\Starter\StartStarterPaymentAction;
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
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
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
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', ['api_key' => 'testkey', 'api_base_url' => 'https://www.signwell.com/api/v1', 'test_mode' => true]);
    config()->set('festilaw.starter.required_documents', ['turnover_proof', 'technical_documentation']);
    app()->forgetInstance(PaymentGatewayRegistry::class);
    app()->forgetInstance(StripePaymentGateway::class);
    app()->forgetInstance(SignatureGatewayInterface::class);
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

it('walks the STARTER journey end-to-end through the UI', function () {
    Storage::fake('local');
    bindSignatureGateway(signed: true); // le prestataire de signature confirmera au retour
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'paid', 'url' => 'https://checkout.stripe.test/cs_1']),
        '*/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/cs_1']),
    ]);

    $submission = openStarterDossier();
    $token = $submission->resume_token;

    // Step 1 - the journey page renders at the sign step.
    get(route('get-started.starter.journey', ['dossier' => $token]))
        ->assertOk()
        ->assertSeeLivewire(StarterJourney::class)
        ->assertSee('Sign your Responsible Person mandate');

    // Clicking "sign" captures the mandate details, starts the signing session and redirects out.
    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->set('incorporationPlace', 'Toronto, Canada')
        ->set('foundingYear', '2015')
        ->set('activity', 'handmade ceramics')
        ->call('sign')
        ->assertRedirect('https://example.com/sign');

    // On return, the provider reports the signature complete -> awaiting documents.
    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('confirmSignature')
        ->assertHasNoErrors();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments);

    // Step 2 - drop both required documents and submit in one go; the dossier then becomes awaiting payment.
    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
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

    // Step 3 - pay: creates a pending payment and redirects out to the Stripe checkout.
    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('pay')
        ->assertRedirect('https://checkout.stripe.test/cs_1');
    expect($submission->fresh()->payments()->where('status', PaymentStatus::Pending)->count())->toBe(1);

    // On return, Stripe reports the session paid -> paid, land on the my-project space.
    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('pollPayment')
        ->assertRedirect(route('my-project', ['dossier' => $token]));
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);

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
    // Une session existe deja (provider_reference pose), le contrat n'est pas encore signe.
    bindSignatureGateway(signed: false);
    $submission = openStarterDossier();
    $submission->contract->update(['signature_provider' => 'stub', 'signature_provider_reference' => 'existing_ref']);

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('sign')
        ->assertRedirect('https://example.com/sign');

    // The reference was not overwritten: no second session/document was created.
    expect($submission->fresh()->contract->signature_provider_reference)->toBe('existing_ref');
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

    bindSignatureGateway(signed: false);

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->set('incorporationPlace', 'Toronto, Canada')
        ->set('foundingYear', '2015')
        ->set('activity', 'the design and sale of ceramics')
        ->call('sign')
        ->assertHasNoErrors()
        ->assertRedirect('https://example.com/sign');

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
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_p', 'status' => 'open', 'url' => 'https://checkout.stripe.test/cs_p']),
        '*/v1/checkout/sessions' => Http::response(['id' => 'cs_p', 'url' => 'https://checkout.stripe.test/cs_p']),
    ]);
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

it('reuses the in-flight checkout at the action level too (anti double-debit backstop)', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    // Creation (POST) et reprise d'une session ouverte (GET par id) bouchonnees.
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'status' => 'open', 'url' => 'https://checkout.stripe.com/c/pay/cs_1']),
        '*/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1']),
    ]);

    // Dossier complet (signe + deux pieces requises) pret a payer, sans paiement encore.
    $submission = openStarterDossier();
    $submission->update(['status' => SubmissionStatus::AwaitingPayment]);
    $submission->contract->update(['signature_status' => SignatureStatus::Signed, 'signed_file_path' => 'contracts/mandate.pdf']);
    foreach (['turnover_proof', 'technical_documentation'] as $type) {
        $submission->uploadedDocuments()->create([
            'type' => $type, 'file_path' => "starter-documents/{$submission->reference}/{$type}.pdf",
            'original_filename' => "{$type}.pdf", 'mime_type' => 'application/pdf', 'size_bytes' => 5, 'uploaded_at' => now(),
        ]);
    }
    $submission = $submission->fresh();

    // Deux demarrages qui franchissent le garde composant (course reelle) : le premier cree le checkout,
    // le second entre dans le verrou, retrouve le paiement en vol et le reutilise -> aucune 2e session/charge.
    $first = app(StartStarterPaymentAction::class)->execute($submission, 'stripe');
    $second = app(StartStarterPaymentAction::class)->execute($submission, 'stripe');

    expect($first->redirectUrl)->toBe('https://checkout.stripe.com/c/pay/cs_1')
        ->and($second->redirectUrl)->toBe('https://checkout.stripe.com/c/pay/cs_1')
        ->and($submission->fresh()->payments()->count())->toBe(1);
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

it('adopts the last display language used as the dossier locale on each journey load', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::InProgress, 'locale' => 'en']);

    app()->setLocale('fr'); // le visiteur bascule en francais pendant le parcours

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()]);

    expect($submission->fresh()->locale)->toBe('fr'); // le dossier suit la derniere langue
});

it('ignores an unsupported display locale for the dossier language', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::InProgress, 'locale' => 'en']);

    app()->setLocale('de'); // langue non supportee -> ignoree

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()]);

    expect($submission->fresh()->locale)->toBe('en'); // inchange
});

/** A dossier at the payment step (documents done, signed) with the given documents already stored. */
function dossierReviewingDocuments(): Submission
{
    $submission = openStarterDossier();
    $submission->update(['status' => SubmissionStatus::AwaitingPayment]);
    $submission->contract->update(['signature_status' => SignatureStatus::Signed, 'signed_file_path' => 'contracts/mandate.pdf']);
    $submission->uploadedDocuments()->create([
        'type' => 'technical_documentation', 'file_path' => "starter-documents/{$submission->reference}/tech.pdf",
        'original_filename' => 'tech.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5, 'uploaded_at' => now(),
    ]);

    return $submission;
}

it('shows download links (and a replace control) when reviewing completed steps', function () {
    $submission = dossierReviewingDocuments();

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('goToStep', 'documents')->assertSet('viewStep', 'documents')  // revue documents
        ->assertSee('tech.pdf')->assertSee(__('Download'))->assertSee(__('Replace'));

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('goToStep', 'sign')->assertSet('viewStep', 'sign')            // revue signature
        ->assertSee(__('Signed mandate'))->assertSee(__('Download'));
});

it('lets the client replace a document while reviewing the completed documents step', function () {
    Storage::fake('local');
    $submission = dossierReviewingDocuments();

    $oldPath = "starter-documents/{$submission->reference}/old.pdf";
    Storage::disk('local')->put($oldPath, 'old-content');
    $doc = $submission->uploadedDocuments()->create([
        'type' => 'turnover_proof', 'file_path' => $oldPath, 'original_filename' => 'old.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 11, 'uploaded_at' => now(),
    ]);

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('goToStep', 'documents')
        ->set('replacements.turnover_proof', UploadedFile::fake()->create('corrected.pdf', 40, 'application/pdf'))
        ->call('replaceDocument', 'turnover_proof')
        ->assertHasNoErrors();

    $doc->refresh();
    expect($doc->original_filename)->toBe('corrected.pdf')       // la piece a bien ete remplacee
        ->and($doc->file_path)->not->toBe($oldPath);
    Storage::disk('local')->assertMissing($oldPath);             // l'ancien fichier est supprime
    Storage::disk('local')->assertExists($doc->file_path);       // le nouveau est stocke

    // Le statut du dossier ne bouge pas : c'est une simple correction.
    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingPayment);
});

it('rejects an invalid replacement file and keeps the original document', function () {
    Storage::fake('local');
    $submission = dossierReviewingDocuments();
    $doc = $submission->uploadedDocuments()->create([
        'type' => 'turnover_proof', 'file_path' => "starter-documents/{$submission->reference}/ok.pdf",
        'original_filename' => 'ok.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5, 'uploaded_at' => now(),
    ]);

    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])
        ->call('goToStep', 'documents')
        ->set('replacements.turnover_proof', UploadedFile::fake()->create('too-big.pdf', 11000, 'application/pdf'))
        ->call('replaceDocument', 'turnover_proof')
        ->assertHasErrors('replacements.turnover_proof');

    expect($doc->fresh()->original_filename)->toBe('ok.pdf'); // inchange
});
