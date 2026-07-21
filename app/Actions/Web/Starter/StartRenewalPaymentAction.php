<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Data\Payment\CheckoutSessionData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;
use App\Services\Billing\RenewalService;
use App\Services\Payment\PaymentGatewayRegistry;

/**
 * Starts an annual renewal payment from the client's dossier space. Unlike the first year (pro rata),
 * a renewal is charged at the FULL annual fee for the service year that is due. Guards on a renewal
 * actually being due (invariant metier -> exception typee). Confirmation arrives via the payment
 * webhook, exactly like the initial payment.
 */
final readonly class StartRenewalPaymentAction
{
    public function __construct(
        private PaymentGatewayRegistry $gateways,
        private RenewalService $renewals,
    ) {}

    public function execute(Submission $submission, string $providerKey): CheckoutSessionData
    {
        $dueYear = $this->renewals->dueYear($submission);

        if ($dueYear === null) {
            throw StarterException::renewalNotDue($submission->id);
        }

        $gateway = $this->gateways->get($providerKey);

        // Plein tarif du pack (aucun prorata sur les renouvellements), pour l'annee de service due.
        $payment = $submission->payments()->create([
            'type' => PaymentType::AnnualRenewal,
            'amount_cents' => $submission->type->annualCents(),
            'service_year' => $dueYear,
            'currency' => 'EUR',
            'provider' => $gateway->key(),
            'status' => PaymentStatus::Pending,
        ]);

        $session = $gateway->createCheckout($payment);
        $payment->update(['provider_reference' => $session->providerReference]);

        return $session;
    }
}
