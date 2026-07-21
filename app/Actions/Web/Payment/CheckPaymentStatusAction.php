<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Data\Payment\PaymentStatusCheckResult;
use App\Enums\Payment\PaymentEventOutcome;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-queries the provider for the live status of a single payment (the "check with the provider now"
 * button shown on a failed payment, client + admin). The provider is the source of truth: if it reports
 * the payment as paid, a false failure is corrected to Succeeded (and the dossier reactivated). Never
 * touches an already-settled payment (Succeeded/Refunded). A transport error is left to the presentation
 * boundary to surface with a friendly message (checkStatus may throw a typed PaymentException).
 */
final readonly class CheckPaymentStatusAction
{
    public function __construct(
        private PaymentGatewayRegistry $gateways,
        private MarkPaymentSucceededAction $markPaymentSucceeded,
    ) {}

    public function execute(Payment $payment): PaymentStatusCheckResult
    {
        // Deja regle (paye ou rembourse) : rien a re-interroger.
        if (in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::Refunded], true)) {
            return new PaymentStatusCheckResult(
                outcome: $payment->status === PaymentStatus::Succeeded ? PaymentEventOutcome::Paid : PaymentEventOutcome::Refunded,
                corrected: false,
                providerReference: $payment->provider_reference,
            );
        }

        // Sans provider connu / sans reference, on ne peut rien re-interroger : provider "injoignable".
        if (! $this->gateways->has((string) $payment->provider) || (string) ($payment->provider_reference ?? '') === '') {
            return new PaymentStatusCheckResult(PaymentEventOutcome::Unresolved, corrected: false, providerReference: $payment->provider_reference, reachable: false);
        }

        try {
            $event = $this->gateways->get((string) $payment->provider)->checkStatus($payment);
        } catch (Throwable $e) {
            // Echec de l'appel prestataire (reseau, session inconnue, config) : etat "injoignable" plutot
            // qu'une erreur generique remontee a l'ecran. On trace le detail technique.
            Log::channel('payments')->warning('Check status: provider query failed.', ['exception' => $e, 'payment' => $payment->id]);

            return new PaymentStatusCheckResult(PaymentEventOutcome::Unresolved, corrected: false, providerReference: $payment->provider_reference, reachable: false);
        }

        if ($event->isPaid()) {
            // Fausse-echec : le provider dit paye -> on corrige (Failed/Expired/Pending/Processing -> Succeeded).
            $this->markPaymentSucceeded->reconcile($payment, $event->providerReference);

            return new PaymentStatusCheckResult(PaymentEventOutcome::Paid, corrected: true, providerReference: $event->providerReference);
        }

        return new PaymentStatusCheckResult($event->outcome, corrected: false, providerReference: $event->providerReference ?: $payment->provider_reference);
    }
}
