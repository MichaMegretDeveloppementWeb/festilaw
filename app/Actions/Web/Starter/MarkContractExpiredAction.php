<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\SignatureStatus;
use App\Models\Contract;

/**
 * Records that the signing document expired without a signature: Pending → Expired. Only a confirmable
 * (Pending) contract transitions · a signed one is never overwritten. The submission stays "in progress"
 * (the sign step) so the client can start a fresh signing session. No longer leaves the contract stuck
 * "pending" forever.
 */
final readonly class MarkContractExpiredAction
{
    public function execute(Contract $contract): Contract
    {
        Contract::query()
            ->whereKey($contract->getKey())
            ->whereIn('signature_status', SignatureStatus::confirmable())
            ->update(['signature_status' => SignatureStatus::Expired]);

        return $contract->refresh();
    }
}
