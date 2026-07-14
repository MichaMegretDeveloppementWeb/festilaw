<?php

use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

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

it('acknowledges a webhook for an unknown reference without failing', function () {
    postJson('/webhooks/payment/fake', ['provider_reference' => 'does-not-exist'])
        ->assertNoContent();
});
