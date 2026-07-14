<?php

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Actions\Web\Starter\StartContractSigningAction;
use App\Actions\Web\Starter\StartStarterPaymentAction;
use App\Actions\Web\Starter\SubmitStarterDocumentsAction;
use App\Data\Payment\CheckoutSessionData;
use App\Data\Signature\SigningSessionData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Starter\StarterException;
use App\Mail\FunnelNotification;
use App\Models\Contract;
use App\Models\Submission;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['fake']);
    config()->set('signature.default', 'fake');
    config()->set('festilaw.starter.required_documents', ['turnover_proof', 'technical_documentation']);
});

it('walks the STARTER happy path end-to-end with the fake providers', function () {
    Mail::fake();

    // 1. Open the file (submission + unsigned contract).
    $submission = app(CreateStarterSubmissionAction::class)->execute([
        'company_name' => 'Wildthread Ceramics',
        'first_name' => 'Maya',
        'last_name' => 'Thornton',
        'email' => 'maya@example.com',
    ]);

    expect($submission->type)->toBe(SubmissionType::Starter)
        ->and($submission->status)->toBe(SubmissionStatus::InProgress)
        ->and($submission->contract)->not->toBeNull();

    // 2. Paying before the dossier is complete is blocked (typed invariant, not a 422).
    expect(fn () => app(StartStarterPaymentAction::class)->execute($submission->fresh(), 'fake'))
        ->toThrow(StarterException::class);

    // 3. Sign the contract (fake provider) then confirm via the webhook action.
    $session = app(StartContractSigningAction::class)->execute($submission->fresh());
    expect($session)->toBeInstanceOf(SigningSessionData::class);

    app(MarkContractSignedAction::class)->execute(
        $submission->contract->fresh(),
        'private/contracts/signed.pdf',
        'sig_ref_123',
    );
    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments);

    // 4. Submit the required documents in one go -> dossier complete -> awaiting payment.
    Storage::fake('local');
    app(SubmitStarterDocumentsAction::class)->execute($submission->fresh(), [
        // createWithContent (pas create) : contenu reel, donc taille lue > 0 sur le fichier stocke.
        'turnover_proof' => UploadedFile::fake()->createWithContent('turnover.pdf', str_repeat('PDF-CONTENT ', 200)),
        'technical_documentation' => UploadedFile::fake()->createWithContent('tech.pdf', str_repeat('PDF-CONTENT ', 200)),
    ]);
    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingPayment);

    // Les fichiers sont bien stockes sur le disque prive, avec leurs metadonnees.
    $stored = $submission->fresh()->uploadedDocuments;
    expect($stored)->toHaveCount(2);
    foreach ($stored as $document) {
        Storage::disk('local')->assertExists($document->file_path);
        expect($document->size_bytes)->toBeGreaterThan(0);
    }

    // 5. Start the payment (fake) -> pending payment + checkout session.
    $checkout = app(StartStarterPaymentAction::class)->execute($submission->fresh(), 'fake');
    expect($checkout)->toBeInstanceOf(CheckoutSessionData::class);

    $payment = $submission->fresh()->payments->first();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount_cents)->toBe(33300)
        ->and($payment->provider)->toBe('fake');

    // 6. Payment webhook confirms -> paid.
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
        'provider' => 'fake',
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
        ->and($status->missingDocuments)->toHaveCount(2);
});
