<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Models\Payment;

/**
 * Records a failed payment (called by the provider webhook, e.g. Stripe async_payment_failed). Only a
 * confirmable payment (Pending/Processing) transitions to Failed · a succeeded/refunded one is never
 * overwritten. The submission stays "awaiting payment", so the buyer can start a fresh attempt. Its audience
 * session is dropped: a payment that does not go through is never counted.
 */
final readonly class MarkPaymentFailedAction
{
    public function execute(Payment $payment): Payment
    {
        Payment::query()
            ->whereKey($payment->getKey())
            ->whereIn('status', PaymentStatus::confirmable())
            ->update(['status' => PaymentStatus::Failed, 'analytics_reference' => null]);

        return $payment->refresh();
    }
}
