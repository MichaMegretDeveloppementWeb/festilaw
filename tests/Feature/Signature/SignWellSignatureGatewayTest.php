<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SigningSessionData;
use App\Exceptions\Signature\SignatureException;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', [
        'api_key' => 'testkey',
        'api_application_id' => null,
        'api_base_url' => 'https://www.signwell.com/api/v1',
        'test_mode' => true,
    ]);
});

/** Builds a SignWell webhook Request with a valid (or overridden) HMAC hash in the body. */
function signwellWebhookRequest(string $type, int $time, string $documentId, string $status, string $apiKey = 'testkey', ?string $hashOverride = null): Request
{
    $hash = $hashOverride ?? hash_hmac('sha256', "{$type}@{$time}", $apiKey);
    $body = json_encode([
        'event' => ['type' => $type, 'time' => $time, 'hash' => $hash],
        'data' => ['object' => ['id' => $documentId, 'status' => $status]],
    ], JSON_THROW_ON_ERROR);

    return Request::create('/webhooks/signature', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
}

it('creates a document from the generated PDF and returns the hosted signing url', function () {
    Http::fake([
        '*/api/v1/documents' => Http::response([
            'id' => 'DOC1',
            'status' => 'Sent',
            'recipients' => [['id' => '1', 'signing_url' => 'https://www.signwell.com/sign/abc']],
        ]),
    ]);

    $submission = Submission::factory()->starter()->create([
        'company_name' => 'Wildthread',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'resume_token' => 'restok',
        'locale' => 'en',
    ]);
    $contract = Contract::factory()->for($submission)->create();

    $session = app(SignatureGatewayInterface::class)->createSigningSession($contract->fresh());

    expect($session)->toBeInstanceOf(SigningSessionData::class)
        ->and($session->providerReference)->toBe('DOC1')
        ->and($session->signingUrl)->toBe('https://www.signwell.com/sign/abc');

    Http::assertSent(function ($req) {
        return str_ends_with($req->url(), '/api/v1/documents')
            && $req->method() === 'POST'
            && $req->hasHeader('X-Api-Key', 'testkey')
            && $req['test_mode'] === true
            && $req['recipients'][0]['email'] === 'jane@example.com';
    });
});

it('confirms a completed signature and downloads the signed PDF with its audit trail', function () {
    Storage::fake('local');
    Http::fake([
        '*/api/v1/documents/*/completed_pdf*' => Http::response('SIGNED-PDF-BYTES', 200, ['Content-Type' => 'application/pdf']),
        '*/api/v1/documents/*' => Http::response(['id' => 'DOC1', 'status' => 'Completed']),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create([
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC1',
    ]);

    $event = app(SignatureGatewayInterface::class)->checkStatus($contract);

    expect($event->signed)->toBeTrue()
        ->and($event->providerReference)->toBe('DOC1')
        ->and($event->signedFilePath)->toBe('contracts/DOC1.pdf');
    Storage::disk('local')->assertExists('contracts/DOC1.pdf');
});

it('reports a signature still pending without downloading anything', function () {
    Http::fake([
        '*/api/v1/documents/*' => Http::response(['id' => 'DOC1', 'status' => 'Sent']),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create(['signature_provider_reference' => 'DOC1']);

    $event = app(SignatureGatewayInterface::class)->checkStatus($contract);

    expect($event->signed)->toBeFalse()
        ->and($event->signedFilePath)->toBeNull();
});

it('parses a completion webhook, verifies the HMAC hash and downloads the signed PDF', function () {
    Storage::fake('local');
    Http::fake([
        '*/api/v1/documents/DOC1/completed_pdf*' => Http::response('SIGNED-PDF-BYTES', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $request = signwellWebhookRequest('document_completed', 1689332249, 'DOC1', 'Completed');

    $event = app(SignatureGatewayInterface::class)->parseWebhook($request);

    expect($event->signed)->toBeTrue()
        ->and($event->providerReference)->toBe('DOC1')
        ->and($event->signedFilePath)->toBe('contracts/DOC1.pdf');
    Storage::disk('local')->assertExists('contracts/DOC1.pdf');
});

it('rejects a webhook whose HMAC hash does not match', function () {
    $request = signwellWebhookRequest('document_completed', 1689332249, 'DOC1', 'Completed', hashOverride: 'not-a-valid-hash');

    expect(fn () => app(SignatureGatewayInterface::class)->parseWebhook($request))
        ->toThrow(SignatureException::class);
});

it('throws a typed exception when SignWell is not configured', function () {
    config()->set('signature.drivers.signwell.api_key', null);

    $submission = Submission::factory()->starter()->create(['resume_token' => 'x']);
    $contract = Contract::factory()->for($submission)->create();

    expect(fn () => app(SignatureGatewayInterface::class)->createSigningSession($contract))
        ->toThrow(SignatureException::class);
});
