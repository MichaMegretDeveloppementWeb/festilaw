<?php

declare(strict_types=1);

namespace App\Data\Payment;

use App\Models\Payment;

/**
 * Controle d'un paiement qui vient d'etre confirme (ReviewConfirmedPaymentAction) : un autre paiement reussi
 * couvrait deja la meme chose (double paiement), et/ou le montant encaisse par le prestataire differe du
 * montant attendu. La confirmation n'est jamais bloquee : ces anomalies sont signalees a Festilaw.
 */
final readonly class PaymentReviewData
{
    public function __construct(
        public ?Payment $duplicateOf,
        public ?int $chargedCents,
        public int $expectedCents,
    ) {}

    public function isDuplicate(): bool
    {
        return $this->duplicateOf !== null;
    }

    public function amountMismatch(): bool
    {
        return $this->chargedCents !== null && $this->chargedCents !== $this->expectedCents;
    }

    public function needsReview(): bool
    {
        return $this->isDuplicate() || $this->amountMismatch();
    }
}
