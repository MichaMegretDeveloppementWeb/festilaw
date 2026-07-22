<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Services\Contract\ContractPdfGenerator;
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

    public function createSignwellDriver(): SignatureGatewayInterface
    {
        return new SignWellSignatureGateway(
            (array) $this->config->get('signature.drivers.signwell', []),
            $this->container->make(ContractPdfGenerator::class),
        );
    }
}
