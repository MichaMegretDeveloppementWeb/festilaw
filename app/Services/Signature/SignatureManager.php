<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureGatewayInterface;
use Illuminate\Support\Manager;

/**
 * Resolves the single active signature provider from config('signature.default'),
 * exactly like Laravel's own Mail/Cache managers. One provider at a time, config-swappable.
 */
final class SignatureManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('signature.default');
    }

    public function createFakeDriver(): SignatureGatewayInterface
    {
        return new FakeSignatureGateway;
    }

    public function createZohoDriver(): SignatureGatewayInterface
    {
        return new ZohoSignatureGateway($this->config->get('signature.drivers.zoho', []));
    }
}
