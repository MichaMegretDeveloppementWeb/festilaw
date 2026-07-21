<?php

declare(strict_types=1);

namespace App\Data\Signature;

use App\Enums\Contract\SignatureEventOutcome;

/**
 * Provider-agnostic result of parsing a signature webhook (or a status poll). `outcome` is the normalized
 * verdict our state machine acts on. The signed PDF is NOT fetched here: it is downloaded once, lazily,
 * by MarkContractSignedAction on the real Pending → Signed transition (no re-download on a replayed
 * webhook or a repeated poll).
 */
final readonly class SignatureWebhookData
{
    public function __construct(
        public string $providerReference,
        public SignatureEventOutcome $outcome,
    ) {}

    /** Optimistic-return / reconcile convenience: is the document fully signed? */
    public function isSigned(): bool
    {
        return $this->outcome === SignatureEventOutcome::Signed;
    }
}
