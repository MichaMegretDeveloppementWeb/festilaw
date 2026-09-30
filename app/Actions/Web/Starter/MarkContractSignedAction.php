<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Contract;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records a completed signature (called by the signature webhook, the return poll, or reconciliation).
 * Idempotent AND concurrency-safe: only the first delivery transitions the state (confirmable → Signed),
 * and the signed PDF is downloaded ONCE · never on a replay (a contract that is no longer Pending returns
 * immediately, before any download). Moves the submission on to payment (documents come first in the
 * journey), or to "awaiting documents" for a dossier signed before uploading them (former order).
 */
final readonly class MarkContractSignedAction
{
    /** Stored statuses of a dossier still before payment: the only ones a signature may move on. */
    private const PRE_PAYMENT_STATUSES = [
        SubmissionStatus::New,
        SubmissionStatus::InProgress,
        SubmissionStatus::AwaitingDocuments,
        SubmissionStatus::AwaitingPayment,
    ];

    public function __construct(
        private SignatureGatewayInterface $signatureGateway,
        private StarterDossierResolver $resolver,
    ) {}

    public function execute(Contract $contract, ?string $providerReference = null): Contract
    {
        // Replay / etat non-confirmable : rien a faire · et surtout aucun re-telechargement du PDF.
        if (! in_array($contract->signature_status, SignatureStatus::confirmable(), true)) {
            return $contract;
        }

        // On tente de recuperer le PDF signe avant la bascule. Avec la signature integree, la confirmation
        // arrive des la fin de la signature, souvent avant que SignWell ait genere le PDF final (404 pendant
        // quelques dizaines de secondes) : l'echec est donc attendu et peripherique (la signature a bien eu
        // lieu). On trace sans bloquer, et la reconciliation rattrape le fichier (backfillSignedDocument).
        $signedFilePath = $this->fetchSignedDocument($contract);

        DB::transaction(function () use ($contract, $signedFilePath, $providerReference): void {
            $affected = Contract::query()
                ->whereKey($contract->getKey())
                ->whereIn('signature_status', SignatureStatus::confirmable())
                ->update([
                    'signature_status' => SignatureStatus::Signed,
                    'signed_file_path' => $signedFilePath,
                    'signature_provider_reference' => $providerReference ?? $contract->signature_provider_reference,
                    'signed_at' => now(),
                ]);

            if ($affected === 0) {
                return;
            }

            // Statut deduit des faits (contrat desormais signe). Jamais sur un dossier annule, paye ou
            // termine : un webhook tardif ne doit pas le faire revenir dans le parcours.
            $submission = $contract->submission()->with(['contract', 'uploadedDocuments'])->first();
            if ($submission !== null && in_array($submission->status, self::PRE_PAYMENT_STATUSES, true)) {
                $submission->update(['status' => $this->resolver->workflowStatus($submission)]);
            }
        });

        return $contract->refresh();
    }

    /**
     * Rattrape le PDF signe d'un contrat DEJA signe dont le fichier local n'a pas pu etre telecharge au
     * moment de la confirmation (echec transitoire du prestataire). La signature ayant eu lieu, on ne
     * retouche NI le statut NI le parcours : on ne fait que re-telecharger le fichier manquant. Idempotent
     * (no-op si le fichier est deja la, si le contrat n'est pas signe, ou sans reference prestataire) et
     * silencieux (aucun evenement : le contrat est deja signe). Appele par la reconciliation.
     */
    public function backfillSignedDocument(Contract $contract): Contract
    {
        if ($contract->signature_status !== SignatureStatus::Signed
            || $contract->signed_file_path !== null
            || $contract->signature_provider_reference === null) {
            return $contract;
        }

        $signedFilePath = $this->fetchSignedDocument($contract);

        if ($signedFilePath !== null) {
            $contract->updateQuietly(['signed_file_path' => $signedFilePath]);
        }

        return $contract->refresh();
    }

    private function fetchSignedDocument(Contract $contract): ?string
    {
        try {
            return $this->signatureGateway->downloadSignedDocument($contract);
        } catch (Throwable $e) {
            Log::channel('signature')->warning('Signed document not downloaded yet (the provider may still be generating it); the reconciliation will backfill it.', [
                'exception' => $e,
                'contract' => $contract->getKey(),
            ]);

            return null;
        }
    }
}
