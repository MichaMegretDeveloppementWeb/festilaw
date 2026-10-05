<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Web\Payment\MarkPaymentRefundedAction;
use App\Actions\Web\Starter\ReverseAppliedPackUpgradeAction;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\PackChangeStatus;
use App\Exceptions\Admin\AdminActionException;
use App\Models\PackChange;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Le "dernier mot" de Festilaw sur un passage au Pro (SC12) : annule la montee et rembourse sa difference.
 * Le remboursement passe d'abord chez le prestataire ; s'il echoue, rien n'est modifie. Ensuite le dossier
 * redevient Creator (ReverseAppliedPackUpgradeAction, attribue a l'admin) et le paiement passe en rembourse :
 * le webhook de ce remboursement, qui suit, ne fait alors plus rien.
 */
final readonly class RevertPackUpgradeAction
{
    public function __construct(
        private PaymentGatewayRegistry $gateways,
        private ReverseAppliedPackUpgradeAction $reverseAppliedPackUpgrade,
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

        Cache::lock('checkout:'.$submission->getKey(), 15)->block(10, function () use ($upgrade, $payment, $adminId): void {
            try {
                $this->gateways->get((string) $payment->provider)->refund($payment);
            } catch (Throwable $e) {
                throw AdminActionException::packUpgradeRefundFailed($upgrade->id, $e);
            }

            DB::transaction(function () use ($upgrade, $payment, $adminId): void {
                $this->reverseAppliedPackUpgrade->execute($upgrade, $adminId);
                $this->markPaymentRefunded->execute($payment);
            });
        });
    }
}
