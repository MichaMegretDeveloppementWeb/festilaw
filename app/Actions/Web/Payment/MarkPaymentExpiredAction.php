<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Models\Payment;

/**
 * Records that a checkout session expired without payment (buyer abandoned): confirmable → Expired.
 * Distinct from Failed (a decline) for back-office clarity. The submission stays "awaiting payment";
 * a retry creates a fresh payment. Only a confirmable payment transitions (never overwrites a settled
 * state · a session that expires *after* an async success must not undo the payment).
 */
final readonly class MarkPaymentExpiredAction
{
    public function execute(Payment $payment): Payment
    {
        Payment::query()
            ->whereKey($payment->getKey())
            ->whereIn('status', PaymentStatus::confirmable())
            ->update(['status' => PaymentStatus::Expired]);

        return $payment->refresh();
    }
}
