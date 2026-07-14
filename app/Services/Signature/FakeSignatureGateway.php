<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Default driver: simulates a signature provider with no external call, so the STARTER
 * flow can be built and tested end-to-end without any credentials. Swap to a real driver
 * (e.g. Zoho) by setting SIGNATURE_DRIVER once keys are available.
 */
final class FakeSignatureGateway implements SignatureGatewayInterface
{
    public function key(): string
    {
        return 'fake';
    }

    public function createSigningSession(Contract $contract): SigningSessionData
    {
        // The signing URL points back into the app so a developer can complete signing locally.
        // It is wired to the dev-completion route when the STARTER funnel is built.
        return new SigningSessionData(
            providerReference: 'fake_'.Str::uuid()->toString(),
            signingUrl: (string) (config('signature.fake.signing_url') ?? url('/')),
        );
    }

    public function parseWebhook(Request $request): SignatureWebhookData
    {
        // Dev: no signature to verify; read the reference (and optional outcome) from the payload.
        return new SignatureWebhookData(
            providerReference: (string) $request->input('provider_reference', ''),
            signed: $request->boolean('signed', true),
            signedFilePath: $request->input('signed_file_path'),
        );
    }
}
