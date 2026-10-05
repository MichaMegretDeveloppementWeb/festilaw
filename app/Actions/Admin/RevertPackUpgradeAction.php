<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Web\Payment\MarkPaymentRefundedAction;
use App\Enums\Contract\ContractRole;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Admin\AdminActionException;
use App\Models\PackChange;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Le "dernier mot" de Festilaw sur un passage au Pro (SC12) : annule la montee et rembourse sa difference.
 * Le remboursement passe d'abord chez le prestataire ; s'il echoue, rien n'est modifie. Ensuite le dossier
 * redevient Creator, son ancien mandat Creator reprend effet et le mandat Pro est conserve (remplace).
 */
final readonly class RevertPackUpgradeAction
{
    public function __construct(
        private PaymentGatewayRegistry $gateways,
        private MarkPaymentRefundedAction $markPaymentRefunded,
    ) {}

    public function execute(PackChange $upgrade, ?int $adminId): void
    {
        $upgrade->loadMissing(['submission', 'payment']);
        $submission = $upgrade->submission;
        $payment = $upgrade->payment;

        if (! $upgrade->isUpgrade() || $upgrade->status !== PackChangeStatus::Completed
            || $payment === null || $payment->status !== PaymentStatus::Succeeded) {
            throw AdminActionException::packUpgradeNotRevertible($upgrade->id);
        }

        Cache::lock('checkout:'.$submission->getKey(), 15)->block(10, function () use ($upgrade, $submission, $payment, $adminId): void {
            try {
                $this->gateways->get((string) $payment->provider)->refund($payment);
            } catch (Throwable $e) {
                throw AdminActionException::packUpgradeRefundFailed($upgrade->id, $e);
            }

            $this->markPaymentRefunded->execute($payment);

            DB::transaction(function () use ($upgrade, $submission, $adminId): void {
                $submission->contracts()->reorder()
                    ->where('role', ContractRole::Current)
                    ->update(['role' => ContractRole::Superseded, 'superseded_at' => now()]);

                $submission->contracts()->reorder()
                    ->where('pack', SubmissionType::Starter)
                    ->where('role', ContractRole::Superseded)
                    ->whereNotNull('signed_at')
                    ->latest('id')
                    ->first()
                    ?->update(['role' => ContractRole::Current, 'superseded_at' => null]);

                $submission->update(['type' => SubmissionType::Starter]);
                $upgrade->update([
                    'status' => PackChangeStatus::Reverted,
                    'decided_by' => $adminId,
                    'decided_at' => now(),
                ]);
            });
        });

        Log::channel('payments')->notice('pack_upgrade.reverted', [
            'pack_change' => $upgrade->id,
            'submission' => $submission->id,
            'payment' => $payment->id,
            'admin' => $adminId,
        ]);
    }
}
