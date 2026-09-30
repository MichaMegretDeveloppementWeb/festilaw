<?php

declare(strict_types=1);

namespace App\Data\Signature;

/**
 * Provider-agnostic output of a signing session: no SignWell/DocuSign object ever leaks upward.
 * `signingUrl` is the embedded signing URL, opened in the provider's iframe on our own page.
 */
final readonly class SigningSessionData
{
    public function __construct(
        public string $providerReference,
        public string $signingUrl,
    ) {}
}
