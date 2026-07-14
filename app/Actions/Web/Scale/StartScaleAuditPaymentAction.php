<?php

declare(strict_types=1);

namespace App\Actions\Web\Scale;

use App\Data\Payment\CheckoutSessionData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;

/**
 * Starts the SCALE audit payment (75 EUR, deducted from the final contract). No dossier gate:
 * paying the audit unlocks the consultation booking. Confirmation arrives via the payment webhook.
 */
final readonly class StartScaleAuditPaymentAction
{
    public function __construct(private PaymentGatewayRegistry $gateways) {}

    public function execute(Submission $submission, string $providerKey): CheckoutSessionData
    {
        $gateway = $this->gateways->get($providerKey);

        // Ecriture unique : pas de transaction.
        $payment = $submission->payments()->create([
            'type' => PaymentType::ScaleAudit,
            'amount_cents' => (int) config('festilaw.scale.audit_amount_cents'),
            'currency' => 'EUR',
            'provider' => $gateway->key(),
            'status' => PaymentStatus::Pending,
        ]);

        $session = $gateway->createCheckout($payment);
        $payment->update(['provider_reference' => $session->providerReference]);

        return $session;
    }
}
