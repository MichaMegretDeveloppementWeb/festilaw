<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SigningSessionData;
use App\Enums\Contract\SignatureStatus;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;
use Illuminate\Support\Facades\Cache;

/**
 * Starts the electronic signature of a STARTER contract via the configured provider (SignWell), and
 * stores the provider reference so the incoming webhook can be matched back.
 *
 * Anti-double-document: an atomic cache lock serialises two concurrent starts (a double-click), so a
 * race can never create two documents at the provider. Inside the lock we re-check for an in-flight,
 * still-signable session and reuse it; a restart after a decline/expiry resets the contract to Pending.
 */
final readonly class StartContractSigningAction
{
    public function __construct(private SignatureGatewayInterface $signatureGateway) {}

    public function execute(Submission $submission): SigningSessionData
    {
        $contract = $submission->contract ?? throw StarterException::contractMissing($submission->id);

        // Le verrou (table cache_locks) ne tient JAMAIS de transaction DB pendant l'appel HTTP au
        // prestataire : il serialise seulement les demarrages concurrents. 15s de bail, 10s d'attente.
        return Cache::lock('contract-signing:'.$contract->getKey(), 15)->block(10, function () use ($contract): SigningSessionData {
            $contract->refresh();

            // Un demarrage concurrent vient peut-etre de creer une session encore signable : on la reutilise.
            if ((string) ($contract->signature_provider_reference ?? '') !== '' && $contract->signature_status === SignatureStatus::Pending) {
                $url = $this->signatureGateway->currentSigningUrl($contract);
                if ($url !== null && $url !== '') {
                    return new SigningSessionData($contract->signature_provider_reference, $url);
                }
            }

            $session = $this->signatureGateway->createSigningSession($contract);

            $contract->update([
                'signature_provider' => $this->signatureGateway->key(),
                'signature_provider_reference' => $session->providerReference,
                // Re-signature apres un refus/expiration : on repasse en attente pour la nouvelle session.
                'signature_status' => SignatureStatus::Pending,
            ]);

            return $session;
        });
    }
}
