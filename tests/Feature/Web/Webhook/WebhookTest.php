<?php

use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['fake']);
    config()->set('signature.default', 'fake');
    Mail::fake();
});

it('confirms a payment from the fake payment webhook', function () {
    $submission = Submission::factory()->starter()->create();
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'fake',
        'provider_reference' => 'fake_ref_1',
        'status' => PaymentStatus::Pending,
    ]);

    postJson('/webhooks/payment/fake', [
        'provider_reference' => 'fake_ref_1',
        'paid' => true,
    ])->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
});

it('marks a contract signed from the fake signature webhook', function () {
    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create([
        'signature_status' => SignatureStatus::Pending,
        'signature_provider' => 'fake',
        'signature_provider_reference' => 'sig_ref_1',
    ]);

    postJson('/webhooks/signature', [
        'provider_reference' => 'sig_ref_1',
        'signed' => true,
        'signed_file_path' => 'private/contracts/signed.pdf',
    ])->assertNoContent();

    expect($contract->fresh()->signature_status)->toBe(SignatureStatus::Signed)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments);
});

it('marks a contract signed from a valid SignWell webhook', function () {
    Storage::fake('local');
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', [
        'api_key' => 'testkey',
        'api_base_url' => 'https://www.signwell.com/api/v1',
        'test_mode' => true,
    ]);
    Http::fake(['*/api/v1/documents/*/completed_pdf*' => Http::response('SIGNED-PDF', 200)]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create([
        'signature_status' => SignatureStatus::Pending,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC1',
    ]);

    $time = 1689332249;
    $hash = hash_hmac('sha256', "document_completed@{$time}", 'testkey');

    postJson('/webhooks/signature', [
        'event' => ['type' => 'document_completed', 'time' => $time, 'hash' => $hash],
        'data' => ['object' => ['id' => 'DOC1', 'status' => 'Completed']],
    ])->assertNoContent();

    expect($contract->fresh()->signature_status)->toBe(SignatureStatus::Signed)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments);
    Storage::disk('local')->assertExists('contracts/DOC1.pdf');
});

it('rejects a SignWell webhook with an invalid signature and leaves the contract untouched', function () {
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', [
        'api_key' => 'testkey',
        'api_base_url' => 'https://www.signwell.com/api/v1',
        'test_mode' => true,
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create([
        'signature_status' => SignatureStatus::Pending,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC1',
    ]);

    postJson('/webhooks/signature', [
        'event' => ['type' => 'document_completed', 'time' => 1689332249, 'hash' => 'not-a-valid-hash'],
        'data' => ['object' => ['id' => 'DOC1', 'status' => 'Completed']],
    ])->assertStatus(400);

    expect($contract->fresh()->signature_status)->toBe(SignatureStatus::Pending)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::InProgress);
});

it('acknowledges a webhook for an unknown reference without failing', function () {
    postJson('/webhooks/payment/fake', ['provider_reference' => 'does-not-exist'])
        ->assertNoContent();
});

it('returns 400 when the targeted payment provider is not enabled', function () {
    config()->set('payment.enabled', ['fake']); // stripe absent

    postJson('/webhooks/payment/stripe', ['provider_reference' => 'x'])
        ->assertStatus(400);
});

it('returns 400 when the payment webhook cannot be verified', function () {
    // Stripe active mais sans secret de webhook : la verification echoue.
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe.webhook_secret', null);

    postJson('/webhooks/payment/stripe', ['provider_reference' => 'x'])
        ->assertStatus(400);
});
