<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SigningSessionData;
use App\Enums\Contract\SignatureEventOutcome;
use App\Exceptions\Signature\SignatureException;
use App\Models\Contract;
use App\Models\Submission;
use App\Repositories\SettingRepository;
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
    // SignWell signe ses webhooks avec l'id du webhook (memorise par festilaw:signwell-webhook).
    app(SettingRepository::class)->put('signwell.webhook_ids', json_encode(['hook_test_1']));
});

/** Builds a SignWell webhook Request with a valid (or overridden) HMAC hash, keyed by the webhook id. */
function signwellWebhookRequest(string $type, int $time, string $documentId, string $status, string $webhookId = 'hook_test_1', ?string $hashOverride = null): Request
{
    $hash = $hashOverride ?? hash_hmac('sha256', "{$type}@{$time}", $webhookId);
    $body = json_encode([
        'event' => ['type' => $type, 'time' => $time, 'hash' => $hash],
        'data' => ['object' => ['id' => $documentId, 'status' => $status]],
    ], JSON_THROW_ON_ERROR);

    return Request::create('/webhooks/signature', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
}

it('creates an embedded document from the generated PDF and returns the embedded signing url', function () {
    Http::fake([
        '*/api/v1/documents' => Http::response([
            'id' => 'DOC1',
            'status' => 'Created',
            'recipients' => [['id' => '1', 'signing_url' => null, 'embedded_signing_url' => 'https://www.signwell.com/docs/abc/sign/']],
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
        ->and($session->signingUrl)->toBe('https://www.signwell.com/docs/abc/sign/');

    // Signature integree (iframe sur notre page) : plus aucune redirection confiee au prestataire.
    Http::assertSent(function ($req) {
        return str_ends_with($req->url(), '/api/v1/documents')
            && $req->method() === 'POST'
            && $req->hasHeader('X-Api-Key', 'testkey')
            && $req['test_mode'] === true
            && $req['embedded_signing'] === true
            && $req['embedded_signing_notifications'] === true
            && ! isset($req['redirect_url'])
            && ! isset($req['decline_redirect_url'])
            && $req['recipients'][0]['email'] === 'jane@example.com';
    });
});

it('throws a typed exception when SignWell returns no embedded signing url', function () {
    Http::fake([
        '*/api/v1/documents' => Http::response([
            'id' => 'DOC1',
            'status' => 'Created',
            'recipients' => [['id' => '1', 'signing_url' => 'https://www.signwell.com/sign/abc']],
        ]),
    ]);

    $submission = Submission::factory()->starter()->create(['locale' => 'en']);
    $contract = Contract::factory()->for($submission)->create();

    app(SignatureGatewayInterface::class)->createSigningSession($contract->fresh());
})->throws(SignatureException::class);

it('detects a completed signature via checkStatus (without downloading anything)', function () {
    Http::fake([
        '*/api/v1/documents/*' => Http::response(['id' => 'DOC1', 'status' => 'Completed']),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create([
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC1',
    ]);

    $event = app(SignatureGatewayInterface::class)->checkStatus($contract);

    expect($event->outcome)->toBe(SignatureEventOutcome::Signed)
        ->and($event->providerReference)->toBe('DOC1');
    // La detection ne telecharge rien : seule la route documents (status) a ete appelee.
    Http::assertNotSent(fn ($req) => str_contains($req->url(), 'completed_pdf'));
});

it('downloads the signed PDF with its audit trail on demand', function () {
    Storage::fake('local');
    Http::fake([
        '*/api/v1/documents/*/completed_pdf*' => Http::response('SIGNED-PDF-BYTES', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create([
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC1',
    ]);

    $path = app(SignatureGatewayInterface::class)->downloadSignedDocument($contract);

    expect($path)->toBe('contracts/DOC1.pdf');
    Storage::disk('local')->assertExists('contracts/DOC1.pdf');
});

it('reports a signature still pending as unresolved', function () {
    Http::fake([
        '*/api/v1/documents/*' => Http::response(['id' => 'DOC1', 'status' => 'Sent']),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create(['signature_provider_reference' => 'DOC1']);

    expect(app(SignatureGatewayInterface::class)->checkStatus($contract)->outcome)
        ->toBe(SignatureEventOutcome::Unresolved);
});

it('returns the in-flight embedded signing url for a document still pending (resume reuse)', function () {
    Http::fake([
        '*/api/v1/documents/*' => Http::response([
            'id' => 'DOC1',
            'status' => 'Created',
            'recipients' => [['id' => '1', 'signing_url' => null, 'embedded_signing_url' => 'https://www.signwell.com/docs/abc/sign/']],
        ]),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create(['signature_provider_reference' => 'DOC1']);

    expect(app(SignatureGatewayInterface::class)->currentSigningUrl($contract))
        ->toBe('https://www.signwell.com/docs/abc/sign/');
});

it('returns null from currentSigningUrl for a legacy non-embedded document (a new embedded session replaces it)', function () {
    Http::fake([
        '*/api/v1/documents/*' => Http::response([
            'id' => 'DOC1',
            'status' => 'Sent',
            'recipients' => [['id' => '1', 'signing_url' => 'https://www.signwell.com/sign/abc', 'embedded_signing_url' => null]],
        ]),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create(['signature_provider_reference' => 'DOC1']);

    expect(app(SignatureGatewayInterface::class)->currentSigningUrl($contract))->toBeNull();
});

it('returns null from currentSigningUrl when the document is already completed', function () {
    Http::fake([
        '*/api/v1/documents/*' => Http::response(['id' => 'DOC1', 'status' => 'Completed']),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create(['signature_provider_reference' => 'DOC1']);

    expect(app(SignatureGatewayInterface::class)->currentSigningUrl($contract))->toBeNull();
});

it('parses a completion webhook and verifies the HMAC hash (detection only, no download)', function () {
    $request = signwellWebhookRequest('document_completed', 1689332249, 'DOC1', 'Completed');

    $event = app(SignatureGatewayInterface::class)->parseWebhook($request);

    expect($event->outcome)->toBe(SignatureEventOutcome::Signed)
        ->and($event->providerReference)->toBe('DOC1');
});

it('maps a declined document to the declined outcome', function () {
    $request = signwellWebhookRequest('document_declined', 1689332249, 'DOC1', 'Declined');

    expect(app(SignatureGatewayInterface::class)->parseWebhook($request)->outcome)
        ->toBe(SignatureEventOutcome::Declined);
});

it('maps an expired document to the expired outcome', function () {
    $request = signwellWebhookRequest('document_expired', 1689332249, 'DOC1', 'Expired');

    expect(app(SignatureGatewayInterface::class)->parseWebhook($request)->outcome)
        ->toBe(SignatureEventOutcome::Expired);
});

it('rejects a webhook whose HMAC hash does not match', function () {
    Http::fake(['*/api/v1/hooks' => Http::response([['id' => 'hook_test_1', 'callback_url' => route('webhooks.signature')]])]);
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
