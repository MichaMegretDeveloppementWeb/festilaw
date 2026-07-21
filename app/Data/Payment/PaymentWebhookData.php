<?php

declare(strict_types=1);

namespace App\Data\Payment;

use App\Enums\Payment\PaymentEventOutcome;

/**
 * Provider-agnostic result of parsing a payment webhook (or a status poll). `outcome` is the normalized
 * verdict our state machine acts on; `clientReference` carries our own Payment id (Stripe
 * client_reference_id / metadata) so the event can be reconciled even if the provider reference was
 * never stored · match by provider ref OR our id.
 */
final readonly class PaymentWebhookData
{
    public function __construct(
        public string $providerReference,
        public PaymentEventOutcome $outcome,
        public ?string $clientReference = null,
    ) {}

    /** Optimistic-return / reconcile convenience: is the payment definitively paid? */
    public function isPaid(): bool
    {
        return $this->outcome === PaymentEventOutcome::Paid;
    }

    /** Is the payment definitively failed (declined / async failure)? */
    public function isFailed(): bool
    {
        return $this->outcome === PaymentEventOutcome::Failed;
    }
}
