<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Exceptions\Signature\SignatureException;
use App\Models\Contract;
use Illuminate\Http\Request;

/**
 * Adapter for Zoho Sign. Structure is in place; only the real HTTP integration remains,
 * to be added the day credentials are provided (SIGNATURE_DRIVER=zoho + ZOHO_SIGN_* env).
 *
 * Until then the default driver is Fake, so this class is never invoked in dev/test.
 */
final class ZohoSignatureGateway implements SignatureGatewayInterface
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config) {}

    public function key(): string
    {
        return 'zoho';
    }

    public function createSigningSession(Contract $contract): SigningSessionData
    {
        if (empty($this->config['api_key']) || empty($this->config['base_url'])) {
            throw SignatureException::providerNotConfigured('zoho');
        }

        // TODO: real Zoho Sign call — create a signing request from the contract's filled fields,
        // then return the request id + the hosted signing URL. Everything upstream already
        // consumes SigningSessionData, so no other layer changes when this is implemented.
        throw SignatureException::providerNotConfigured('zoho');
    }

    public function parseWebhook(Request $request): SignatureWebhookData
    {
        if (empty($this->config['api_key'])) {
            throw SignatureException::providerNotConfigured('zoho');
        }

        // TODO: verify the Zoho webhook, then map the completion event to a SignatureWebhookData
        // (provider reference + signed document location).
        throw SignatureException::providerNotConfigured('zoho');
    }
}
