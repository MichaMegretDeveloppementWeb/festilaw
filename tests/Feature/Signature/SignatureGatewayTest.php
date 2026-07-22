<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Services\Signature\SignWellSignatureGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the SignWell signature gateway from config', function () {
    config()->set('signature.default', 'signwell');
    app()->forgetInstance(SignatureGatewayInterface::class);

    $gateway = app(SignatureGatewayInterface::class);

    expect($gateway)->toBeInstanceOf(SignWellSignatureGateway::class)
        ->and($gateway->key())->toBe('signwell');
});
