<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records a completed signature (called by the signature webhook, the return poll, or reconciliation).
 * Idempotent AND concurrency-safe: only the first delivery transitions the state (confirmable → Signed),
 * and the signed PDF is downloaded ONCE — never on a replay (a contract that is no longer Pending returns
 * immediately, before any download). Advances the submission to "awaiting documents".
 */
final readonly class MarkContractSignedAction
{
    public function __construct(private SignatureGatewayInterface $signatureGateway) {}

    public function execute(Contract $contract, ?string $providerReference = null): Contract
    {
        // Replay / etat non-confirmable : rien a faire — et surtout aucun re-telechargement du PDF.
        if (! in_array($contract->signature_status, SignatureStatus::confirmable(), true)) {
            return $contract;
        }

        // Le PDF signe existe deja chez le prestataire : on le recupere avant la bascule. Un echec de
        // telechargement est peripherique (le document reste recuperable) : on trace sans bloquer la
        // confirmation, le fichier local pourra etre re-recupere ensuite.
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

            $contract->submission()->update(['status' => SubmissionStatus::AwaitingDocuments]);
        });

        return $contract->refresh();
    }

    private function fetchSignedDocument(Contract $contract): ?string
    {
        try {
            return $this->signatureGateway->downloadSignedDocument($contract);
        } catch (Throwable $e) {
            Log::channel('signature')->error('Failed to download the signed document.', [
                'exception' => $e,
                'contract' => $contract->getKey(),
            ]);

            return null;
        }
    }
}
