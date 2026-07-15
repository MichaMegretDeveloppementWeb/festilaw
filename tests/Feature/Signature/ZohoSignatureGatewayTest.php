<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SigningSessionData;
use App\Exceptions\Signature\SignatureException;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config()->set('signature.default', 'zoho');
    config()->set('signature.drivers.zoho', [
        'client_id' => 'cid',
        'client_secret' => 'csecret',
        'refresh_token' => 'rtoken',
        'webhook_secret' => 'whsecret',
        'accounts_url' => 'https://accounts.zoho.eu',
        'api_base_url' => 'https://sign.zoho.eu/api/v1',
        'testing' => true,
    ]);
});

/** Builds a valid Zoho webhook Request with the correct HMAC header. */
function zohoWebhookRequest(array $payload, string $secret = 'whsecret', ?string $signatureOverride = null): Request
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = $signatureOverride ?? base64_encode(hash_hmac('sha256', $body, $secret, true));

    return Request::create('/webhooks/signature', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ZS_WEBHOOK_SIGNATURE' => $signature,
    ], $body);
}

it('creates a signing request by uploading the generated contract PDF', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/api/v1/requests/*/submit*' => Http::response(['status' => 'success']),
        '*/api/v1/requests/*/embedtoken*' => Http::response(['sign_url' => 'https://sign.zoho.eu/embed/xyz']),
        '*/api/v1/requests' => Http::response([
            'requests' => ['request_id' => 'REQ1', 'actions' => [['action_id' => 'ACT1']]],
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
        ->and($session->providerReference)->toBe('REQ1')
        ->and($session->signingUrl)->toBe('https://sign.zoho.eu/embed/xyz');

    // Le document a bien ete cree par upload (POST multipart /requests) puis envoye.
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/api/v1/requests') && $req->method() === 'POST');
    Http::assertSent(fn ($req) => str_contains($req->url(), '/api/v1/requests/REQ1/submit'));
});

it('confirms a completed signature and downloads the signed PDF', function () {
    Storage::fake('local');
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/api/v1/requests/*/pdf*' => Http::response('SIGNED-PDF-BYTES', 200, ['Content-Type' => 'application/pdf']),
        '*/api/v1/requests/*' => Http::response(['requests' => ['request_status' => 'completed']]),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create([
        'signature_provider' => 'zoho',
        'signature_provider_reference' => 'REQ1',
    ]);

    $event = app(SignatureGatewayInterface::class)->checkStatus($contract);

    expect($event->signed)->toBeTrue()
        ->and($event->providerReference)->toBe('REQ1')
        ->and($event->signedFilePath)->toBe('contracts/REQ1.pdf');
    Storage::disk('local')->assertExists('contracts/REQ1.pdf');
});

it('reports a signature still in progress without downloading anything', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/api/v1/requests/*' => Http::response(['requests' => ['request_status' => 'inprogress']]),
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create(['signature_provider_reference' => 'REQ1']);

    $event = app(SignatureGatewayInterface::class)->checkStatus($contract);

    expect($event->signed)->toBeFalse()
        ->and($event->signedFilePath)->toBeNull();
});

it('parses a completion webhook, verifies HMAC and downloads the signed PDF', function () {
    Storage::fake('local');
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/api/v1/requests/REQ1/pdf*' => Http::response('SIGNED-PDF-BYTES', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $request = zohoWebhookRequest([
        'notifications' => ['operation_type' => 'RequestCompleted'],
        'requests' => ['request_id' => 'REQ1', 'request_status' => 'completed'],
    ]);

    $event = app(SignatureGatewayInterface::class)->parseWebhook($request);

    expect($event->signed)->toBeTrue()
        ->and($event->providerReference)->toBe('REQ1')
        ->and($event->signedFilePath)->toBe('contracts/REQ1.pdf');
    Storage::disk('local')->assertExists('contracts/REQ1.pdf');
});

it('rejects a webhook with an invalid HMAC signature', function () {
    $request = zohoWebhookRequest(
        ['notifications' => ['operation_type' => 'RequestCompleted'], 'requests' => ['request_id' => 'REQ1', 'request_status' => 'completed']],
        signatureOverride: 'not-a-valid-signature',
    );

    expect(fn () => app(SignatureGatewayInterface::class)->parseWebhook($request))
        ->toThrow(SignatureException::class);
});

it('throws a typed exception when Zoho is not configured', function () {
    config()->set('signature.drivers.zoho.refresh_token', null);

    $submission = Submission::factory()->starter()->create(['resume_token' => 'x']);
    $contract = Contract::factory()->for($submission)->create();

    expect(fn () => app(SignatureGatewayInterface::class)->createSigningSession($contract))
        ->toThrow(SignatureException::class);
});
