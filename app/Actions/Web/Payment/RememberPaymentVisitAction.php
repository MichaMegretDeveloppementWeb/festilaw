<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Models\Payment;

/**
 * Garde sur un paiement encore en attente la reference de la visite du client, revenu de Stripe : la derniere
 * reference prise l'emporte sur celle du clic sur « payer » (Falcon Analytics 599edd0). L'encaissement, souvent
 * confirme par le webhook, compte alors le paiement dans cette visite. Rien sur un paiement deja regle, dont la
 * reference a servi et a ete videe, ni sans reference (client non mesure).
 */
final readonly class RememberPaymentVisitAction
{
    public function execute(Payment $payment, ?string $reference): void
    {
        if ($reference === null) {
            return;
        }

        Payment::query()
            ->whereKey($payment->getKey())
            ->whereIn('status', PaymentStatus::confirmable())
            ->update(['analytics_reference' => $reference]);
    }
}
