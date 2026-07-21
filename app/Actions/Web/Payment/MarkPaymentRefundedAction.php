<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Records a refund or chargeback on a previously successful payment: Succeeded → Refunded. Only a
 * succeeded payment transitions (idempotent on redelivery; never touches a non-succeeded row). The
 * dossier's active state is *derived* from its non-refunded succeeded subscription payments, so writing
 * Refunded here is what deactivates it — no separate submission write. Logged at warning level because a
 * refund/chargeback is exceptional and support-relevant.
 */
final readonly class MarkPaymentRefundedAction
{
    public function execute(Payment $payment): Payment
    {
        $affected = Payment::query()
            ->whereKey($payment->getKey())
            ->where('status', PaymentStatus::Succeeded)
            ->update(['status' => PaymentStatus::Refunded]);

        if ($affected > 0) {
            Log::channel('payments')->warning('Payment.refunded', [
                'payment' => $payment->getKey(),
                'submission' => $payment->submission_id,
                'provider' => $payment->provider,
            ]);
        }

        return $payment->refresh();
    }
}
