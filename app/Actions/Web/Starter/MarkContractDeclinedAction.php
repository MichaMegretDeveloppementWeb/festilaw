<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\SignatureStatus;
use App\Models\Contract;

/**
 * Records that the signer declined (or the sender canceled) the document: Pending → Declined. Only a
 * confirmable (Pending) contract transitions · a signed one is never overwritten. The submission stays
 * "in progress" (the sign step) so the client can start a fresh signing session; a banner surfaces the
 * refusal. No longer leaves the contract stuck "pending" forever.
 */
final readonly class MarkContractDeclinedAction
{
    public function execute(Contract $contract): Contract
    {
        Contract::query()
            ->whereKey($contract->getKey())
            ->whereIn('signature_status', SignatureStatus::confirmable())
            ->update(['signature_status' => SignatureStatus::Declined]);

        return $contract->refresh();
    }
}
