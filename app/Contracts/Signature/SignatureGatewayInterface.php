<?php

declare(strict_types=1);

namespace App\Contracts\Signature;

use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Models\Contract;
use Illuminate\Http\Request;

/**
 * A single electronic-signature provider is active at a time, selected by config
 * (Strategy resolved by a Manager). Swapping provider is a config change, not a code change.
 */
interface SignatureGatewayInterface
{
    /** Identifier of the active provider (e.g. 'signwell', 'fake'). */
    public function key(): string;

    /** Start a signing session for the given contract and return where the signer must go. */
    public function createSigningSession(Contract $contract): SigningSessionData;

    /**
     * The signing URL of the session already in flight for this contract, if one exists and is still
     * signable (so a resume reuses it instead of creating a duplicate document). Null if none / not
     * reusable, in which case the caller starts a fresh session.
     */
    public function currentSigningUrl(Contract $contract): ?string;

    /**
     * Poll the provider for the current signature status of the contract (used to confirm completion
     * when the signer returns, without relying on a webhook). Downloads the signed document if done.
     */
    public function checkStatus(Contract $contract): SignatureWebhookData;

    /** Verify + parse an incoming provider webhook. Throws on an invalid/untrusted payload. */
    public function parseWebhook(Request $request): SignatureWebhookData;
}
