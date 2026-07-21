<?php

declare(strict_types=1);

namespace App\Data\Payment;

use App\Enums\Payment\PaymentEventOutcome;

/**
 * Result of a deliberate "check this payment against the provider now" (the failed-payment re-query
 * button, client + admin). `outcome` is what the provider says right now; `corrected` is true when a
 * payment that was NOT succeeded turned out to be paid and was reconciled to Succeeded (a false failure).
 */
final readonly class PaymentStatusCheckResult
{
    public function __construct(
        public PaymentEventOutcome $outcome,
        public bool $corrected,
        public ?string $providerReference,
    ) {}

    /** The provider confirms the payment is paid (whether we just corrected it or it already was). */
    public function confirmedPaid(): bool
    {
        return $this->outcome === PaymentEventOutcome::Paid;
    }
}
