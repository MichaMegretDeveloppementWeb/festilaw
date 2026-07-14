<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SigningSessionData;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;

/**
 * Starts the electronic signature of a STARTER contract via the configured provider (Fake by
 * default), and stores the provider reference so the incoming webhook can be matched back.
 */
final readonly class StartContractSigningAction
{
    public function __construct(private SignatureGatewayInterface $signatureGateway) {}

    public function execute(Submission $submission): SigningSessionData
    {
        $contract = $submission->contract ?? throw StarterException::contractMissing($submission->id);

        $session = $this->signatureGateway->createSigningSession($contract);

        // Ecriture unique : pas de transaction (cf. architecture-couches, pragmatisme).
        $contract->update([
            'signature_provider' => $this->signatureGateway->key(),
            'signature_provider_reference' => $session->providerReference,
        ]);

        return $session;
    }
}
