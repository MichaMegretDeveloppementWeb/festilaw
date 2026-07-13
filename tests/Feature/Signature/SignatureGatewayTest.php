<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SigningSessionData;
use App\Models\Contract;
use App\Services\Signature\FakeSignatureGateway;
use App\Services\Signature\ZohoSignatureGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the fake signature gateway by default, without any credentials', function () {
    config()->set('signature.default', 'fake');

    $gateway = app(SignatureGatewayInterface::class);

    expect($gateway)->toBeInstanceOf(FakeSignatureGateway::class)
        ->and($gateway->key())->toBe('fake');
});

it('creates a signing session without any external call', function () {
    config()->set('signature.default', 'fake');
    $contract = Contract::factory()->create();

    $session = app(SignatureGatewayInterface::class)->createSigningSession($contract);

    expect($session)->toBeInstanceOf(SigningSessionData::class)
        ->and($session->providerReference)->toStartWith('fake_')
        ->and($session->signingUrl)->not->toBeEmpty();
});

it('swaps the active provider through config alone', function () {
    config()->set('signature.default', 'zoho');

    $gateway = app(SignatureGatewayInterface::class);

    expect($gateway)->toBeInstanceOf(ZohoSignatureGateway::class)
        ->and($gateway->key())->toBe('zoho');
});
