<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\ContractRole;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\PackChangeStatus;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;
use App\Services\Web\Starter\PackChangeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Le client renonce au passage au Pro avant de l'avoir paye (SC12) : la montee est abandonnee, le mandat Pro
 * eventuellement signe est conserve comme trace (remplace). Refuse pendant qu'un paiement est en cours de
 * confirmation. Si le client paie malgre tout un checkout reste ouvert, la montee est appliquee a la
 * confirmation (ApplyPackUpgradeAction) : l'argent recu fait foi.
 */
final readonly class CancelPackUpgradeAction
{
    public function __construct(private PackChangeService $packChanges) {}

    public function execute(Submission $submission): void
    {
        Cache::lock('checkout:'.$submission->getKey(), 15)->block(10, function () use ($submission): void {
            $upgrade = $this->packChanges->openUpgrade($submission);
            if ($upgrade === null) {
                return;
            }

            if ($submission->payments()->where('type', PaymentType::PackUpgrade)->where('status', PaymentStatus::Processing)->exists()) {
                throw StarterException::packUpgradeUnavailable($submission->id, 'an upgrade payment is awaiting confirmation');
            }

            DB::transaction(function () use ($upgrade): void {
                $upgrade->update(['status' => PackChangeStatus::Cancelled]);
                $upgrade->contract?->update(['role' => ContractRole::Superseded, 'superseded_at' => now()]);
            });
        });
    }
}
