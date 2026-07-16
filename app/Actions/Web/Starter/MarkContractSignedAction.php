<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Records a completed signature (called by the signature webhook). Idempotent AND concurrency-safe:
 * only the first of two redelivered webhooks transitions the state. Advances the submission
 * to "awaiting documents".
 */
final readonly class MarkContractSignedAction
{
    public function execute(Contract $contract, ?string $signedFilePath = null, ?string $providerReference = null): Contract
    {
        DB::transaction(function () use ($contract, $signedFilePath, $providerReference): void {
            $affected = Contract::query()
                ->whereKey($contract->getKey())
                ->where('signature_status', '!=', SignatureStatus::Signed)
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
}
