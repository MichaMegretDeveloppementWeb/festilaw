<?php

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Actions\Web\Starter\StartContractSigningAction;
use App\Actions\Web\Starter\StartStarterPaymentAction;
use App\Actions\Web\Starter\SubmitStarterDocumentsAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Payment\CheckoutSessionData;
use App\Data\Signature\SigningSessionData;
use App\Enums\Document\DocumentType;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Starter\StarterException;
use App\Mail\FunnelNotification;
use App\Models\Contract;
use App\Models\Submission;
use App\Models\UploadedDocument;
use App\Services\Billing\AnnualFeeProrator;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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
});

it('walks the STARTER happy path end-to-end', function () {
    Mail::fake();
    // Prestataires bouchonnes : la signature (createSigningSession) sans HTTP, le checkout Stripe via Http::fake.
    $signature = Mockery::mock(SignatureGatewayInterface::class);
    $signature->shouldReceive('key')->andReturn('signwell');
    $signature->shouldReceive('createSigningSession')->andReturn(new SigningSessionData('doc_stub', 'https://signwell.test/sign'));
    $signature->shouldReceive('downloadSignedDocument')->andReturnNull();
    app()->instance(SignatureGatewayInterface::class, $signature);
    Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_stub', 'url' => 'https://checkout.stripe.test/cs_stub'])]);

    // 1. Open the file (submission + unsigned contract).
    $outcome = app(CreateStarterSubmissionAction::class)->execute([
        'company_name' => 'Wildthread Ceramics',
        'first_name' => 'Maya',
        'last_name' => 'Thornton',
        'email' => 'maya@example.com',
    ]);

    expect($outcome->isNew)->toBeTrue();
    $submission = $outcome->submission;

    expect($submission->type)->toBe(SubmissionType::Starter)
        ->and($submission->status)->toBe(SubmissionStatus::InProgress)
        ->and($submission->contract)->not->toBeNull();

    // 2. Paying before the dossier is complete is blocked (typed invariant, not a 422).
    expect(fn () => app(StartStarterPaymentAction::class)->execute($submission->fresh(), 'stripe'))
        ->toThrow(StarterException::class);

    // 3. Documents come first: no signing session is started (nor billed) while one is missing.
    expect(fn () => app(StartContractSigningAction::class)->execute($submission->fresh()))
        ->toThrow(StarterException::class);
    expect($submission->fresh()->contract->signature_provider_reference)->toBeNull();

    // 4. Submit the required documents in one go -> still to be signed (in progress).
    Storage::fake('local');
    app(SubmitStarterDocumentsAction::class)->execute($submission->fresh(), [
        // createWithContent (pas create) : contenu reel, donc taille lue > 0 sur le fichier stocke.
        'turnover_proof' => UploadedFile::fake()->createWithContent('turnover.pdf', str_repeat('PDF-CONTENT ', 200)),
        'technical_documentation' => UploadedFile::fake()->createWithContent('tech.pdf', str_repeat('PDF-CONTENT ', 200)),
    ]);
    expect($submission->fresh()->status)->toBe(SubmissionStatus::InProgress);

    // Les fichiers sont bien stockes sur le disque prive, avec leurs metadonnees.
    $stored = $submission->fresh()->uploadedDocuments;
    expect($stored)->toHaveCount(2);
    foreach ($stored as $document) {
        Storage::disk('local')->assertExists($document->file_path);
        expect($document->size_bytes)->toBeGreaterThan(0);
    }

    // 5. Sign the contract then confirm via the webhook action -> dossier complete -> awaiting payment.
    $session = app(StartContractSigningAction::class)->execute($submission->fresh());
    expect($session)->toBeInstanceOf(SigningSessionData::class);

    app(MarkContractSignedAction::class)->execute(
        $submission->contract->fresh(),
        'sig_ref_123',
    );
    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingPayment);

    // 6. Start the payment (Stripe) -> pending payment + checkout session.
    $checkout = app(StartStarterPaymentAction::class)->execute($submission->fresh(), 'stripe');
    expect($checkout)->toBeInstanceOf(CheckoutSessionData::class);

    // Annee 1 au prorata de la date de signature (cf. contrat), pas le tarif plein.
    $expectedCents = app(AnnualFeeProrator::class)
        ->firstYearCents(33300, $submission->fresh()->contract->signed_at);

    $payment = $submission->fresh()->payments->first();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount_cents)->toBe($expectedCents)
        ->and($payment->provider)->toBe('stripe');

    // 7. Payment webhook confirms -> paid.
    app(MarkPaymentSucceededAction::class)->execute($payment->fresh(), 'pay_ref_456');
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);

    Mail::assertSent(FunnelNotification::class);
});

it('is idempotent when a payment webhook is redelivered', function () {
    Mail::fake();

    $submission = Submission::factory()->starter()->create();
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    app(MarkPaymentSucceededAction::class)->execute($payment, 'other_ref');

    // Already succeeded: no change, no second notification.
    expect($payment->fresh()->provider_reference)->not->toBe('other_ref');
    Mail::assertNothingSent();
});

it('converts a filesystem failure into a typed exception (never a raw error)', function () {
    $submission = Submission::factory()->starter()->create();
    Contract::factory()->for($submission)->signed()->create();

    // Le stockage echoue (putFileAs renvoie false) : l'Action doit lever une StarterException typee,
    // pas laisser fuir une erreur Flysystem/IO non geree.
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('putFileAs')->andReturn(false);
    $factory = Mockery::mock(FilesystemFactory::class);
    $factory->shouldReceive('disk')->andReturn($disk);
    app()->instance(FilesystemFactory::class, $factory);

    expect(fn () => app(SubmitStarterDocumentsAction::class)->execute($submission, [
        'turnover_proof' => UploadedFile::fake()->create('turnover.pdf', 100, 'application/pdf'),
        'technical_documentation' => UploadedFile::fake()->create('tech.pdf', 100, 'application/pdf'),
    ]))->toThrow(StarterException::class);
});

it('reports missing documents through the resolver', function () {
    $submission = Submission::factory()->starter()->create();
    Contract::factory()->for($submission)->signed()->create();
    $submission->load(['contract', 'uploadedDocuments']);

    $status = app(StarterDossierResolver::class)->resolve($submission);

    expect($status->contractSigned)->toBeTrue()
        ->and($status->isComplete)->toBeFalse()
        ->and($status->missingDocuments)->toHaveCount(2)
        ->and($status->nextStep)->toBe('documents'); // signe dans l'ancien ordre : les pieces d'abord
});

it('derives the next step and the stored status from the facts, in the documents -> signature -> payment order', function (bool $withDocuments, bool $signed, string $nextStep, SubmissionStatus $workflowStatus) {
    $submission = Submission::factory()->starter()->create();
    $signed
        ? Contract::factory()->for($submission)->signed()->create()
        : Contract::factory()->for($submission)->create();
    if ($withDocuments) {
        foreach (['turnover_proof', 'technical_documentation'] as $type) {
            UploadedDocument::factory()->for($submission)->create(['type' => $type]);
        }
    }
    $submission->load(['contract', 'uploadedDocuments']);

    $resolver = app(StarterDossierResolver::class);

    expect($resolver->resolve($submission)->nextStep)->toBe($nextStep)
        ->and($resolver->workflowStatus($submission))->toBe($workflowStatus);
})->with([
    'nothing done yet' => [false, false, 'documents', SubmissionStatus::InProgress],
    'documents uploaded' => [true, false, 'sign', SubmissionStatus::InProgress],
    'signed first (former order)' => [false, true, 'documents', SubmissionStatus::AwaitingDocuments],
    'complete' => [true, true, 'payment', SubmissionStatus::AwaitingPayment],
]);

it('reads the required documents per pack', function () {
    // Valeurs livrees par config/festilaw.php : Pro = documentation technique seule (Festilaw, 04/10/2026).
    expect(SubmissionType::Starter->requiredDocuments())->toBe([DocumentType::TurnoverProof, DocumentType::TechnicalDocumentation])
        ->and(SubmissionType::Pro->requiredDocuments())->toBe([DocumentType::TechnicalDocumentation])
        ->and(SubmissionType::Scale->requiredDocuments())->toBe([]);

    // Un dossier Pro avec la seule piece exigee pour son pack est complet cote pieces.
    $pro = Submission::factory()->pro()->create();
    Contract::factory()->for($pro)->signed()->create();
    UploadedDocument::factory()->for($pro)->create(['type' => 'technical_documentation']);
    $pro->load(['contract', 'uploadedDocuments']);

    expect(app(StarterDossierResolver::class)->resolve($pro)->isComplete)->toBeTrue();
});
