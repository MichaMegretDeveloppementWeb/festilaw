<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Models\Payment;

/**
 * Records that an async payment (Klarna/Bancontact/MB WAY) was accepted and is awaiting settlement:
 * Pending → Processing. The buyer's money is not captured yet, so the submission stays "awaiting
 * payment"; the later async_payment_succeeded / _failed webhook settles it. Only a Pending payment
 * transitions (never overwrites a settled state).
 */
final readonly class MarkPaymentProcessingAction
{
    public function execute(Payment $payment): Payment
    {
        Payment::query()
            ->whereKey($payment->getKey())
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => PaymentStatus::Processing]);

        return $payment->refresh();
    }
}
