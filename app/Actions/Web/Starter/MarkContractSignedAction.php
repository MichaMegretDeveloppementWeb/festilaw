<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Records a completed signature (called by the Zoho webhook). Idempotent: a re-delivered
 * webhook is a no-op. Advances the submission to "awaiting documents".
 */
final readonly class MarkContractSignedAction
{
    public function execute(Contract $contract, ?string $signedFilePath = null, ?string $providerReference = null): Contract
    {
        if ($contract->signature_status === SignatureStatus::Signed) {
            return $contract;
        }

        DB::transaction(function () use ($contract, $signedFilePath, $providerReference): void {
            $contract->update([
                'signature_status' => SignatureStatus::Signed,
                'signed_file_path' => $signedFilePath,
                'signature_provider_reference' => $providerReference ?? $contract->signature_provider_reference,
                'signed_at' => now(),
            ]);

            $contract->submission()->update(['status' => SubmissionStatus::AwaitingDocuments]);
        });

        return $contract->refresh();
    }
}
