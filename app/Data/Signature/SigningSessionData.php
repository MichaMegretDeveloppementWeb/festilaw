<?php

declare(strict_types=1);

namespace App\Data\Signature;

/**
 * Provider-agnostic output of a signing session: no Zoho/DocuSign object ever leaks upward.
 */
final readonly class SigningSessionData
{
    public function __construct(
        public string $providerReference,
        public string $signingUrl,
    ) {}
}
