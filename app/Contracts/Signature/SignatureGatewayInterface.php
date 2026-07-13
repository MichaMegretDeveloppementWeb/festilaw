<?php

declare(strict_types=1);

namespace App\Contracts\Signature;

use App\Data\Signature\SigningSessionData;
use App\Models\Contract;

/**
 * A single electronic-signature provider is active at a time, selected by config
 * (Strategy resolved by a Manager). Swapping provider is a config change, not a code change.
 */
interface SignatureGatewayInterface
{
    /** Identifier of the active provider (e.g. 'zoho', 'fake'). */
    public function key(): string;

    /** Start a signing session for the given contract and return where the signer must go. */
    public function createSigningSession(Contract $contract): SigningSessionData;
}
