<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Enums\Contract\SignatureEventOutcome;
use App\Enums\Contract\SignatureStatus;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Default driver: simulates a signature provider with no external call, so the STARTER
 * flow can be built and tested end-to-end without any credentials. Swap to a real driver
 * (e.g. SignWell) by setting SIGNATURE_DRIVER once keys are available.
 */
final class FakeSignatureGateway implements SignatureGatewayInterface
{
    public function key(): string
    {
        return 'fake';
    }

    public function createSigningSession(Contract $contract): SigningSessionData
    {
        return new SigningSessionData(
            providerReference: 'fake_'.Str::uuid()->toString(),
            signingUrl: $this->devSigningUrl($contract),
        );
    }

    /**
     * The Fake stands in for the provider's hosted signing page: it sends the signer to the local
     * dev-completion route for their dossier. An explicit env override wins; a token-less dossier
     * falls back to the home page.
     */
    private function devSigningUrl(Contract $contract): string
    {
        $configured = config('signature.fake.signing_url');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $token = $contract->submission?->resume_token;

        return $token !== null
            ? route('get-started.starter.dev-sign', ['dossier' => $token])
            : url('/');
    }

    public function currentSigningUrl(Contract $contract): ?string
    {
        // Reutilisable seulement tant que la signature est en attente (comme SignWell exclut Declined/
        // Expired/Signed) : apres un refus/expiration on recreera une session, pas de reprise d'un mort.
        if ((string) ($contract->signature_provider_reference ?? '') === '' || $contract->signature_status !== SignatureStatus::Pending) {
            return null;
        }

        return $this->devSigningUrl($contract);
    }

    public function checkStatus(Contract $contract): SignatureWebhookData
    {
        // Reflete le statut reel : le Fake se complete via la route dev-sign, pas par polling.
        return new SignatureWebhookData(
            providerReference: (string) ($contract->signature_provider_reference ?? ''),
            outcome: $contract->signature_status === SignatureStatus::Signed
                ? SignatureEventOutcome::Signed
                : SignatureEventOutcome::Unresolved,
        );
    }

    public function parseWebhook(Request $request): SignatureWebhookData
    {
        // Dev: no signature to verify; read the reference (and optional outcome) from the payload.
        return new SignatureWebhookData(
            providerReference: (string) $request->input('provider_reference', ''),
            outcome: $this->fakeOutcome($request),
        );
    }

    /** No downloadable file for the Fake driver (the dev completion sets no signed PDF). */
    public function downloadSignedDocument(Contract $contract): ?string
    {
        return null;
    }

    /** Dev payload → outcome: `outcome=signed|declined|expired|unresolved`, or the legacy `signed` boolean. */
    private function fakeOutcome(Request $request): SignatureEventOutcome
    {
        if ($request->filled('outcome')) {
            return match ((string) $request->input('outcome')) {
                'declined' => SignatureEventOutcome::Declined,
                'expired' => SignatureEventOutcome::Expired,
                'unresolved' => SignatureEventOutcome::Unresolved,
                default => SignatureEventOutcome::Signed,
            };
        }

        return $request->boolean('signed', true) ? SignatureEventOutcome::Signed : SignatureEventOutcome::Unresolved;
    }
}
