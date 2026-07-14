<?php

declare(strict_types=1);

namespace App\Data\Signature;

/**
 * Provider-agnostic result of parsing a signature webhook.
 */
final readonly class SignatureWebhookData
{
    public function __construct(
        public string $providerReference,
        public bool $signed,
        public ?string $signedFilePath = null,
    ) {}
}
