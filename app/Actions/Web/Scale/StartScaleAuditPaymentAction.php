<?php

declare(strict_types=1);

namespace App\Actions\Web\Scale;

use App\Data\Payment\CheckoutSessionData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Exceptions\Scale\ScaleException;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Starts the SCALE audit payment (75 EUR, deducted from the final contract). Paying the audit unlocks the
 * consultation booking; confirmation arrives via the payment webhook (and the poll-on-return). Guards
 * against paying twice (audit already paid) and reuses an in-flight checkout (anti double-debit).
 */
final readonly class StartScaleAuditPaymentAction
{
    public function __construct(private PaymentGatewayRegistry $gateways) {}

    public function execute(Submission $submission, string $providerKey): CheckoutSessionData
    {
        // Audit deja regle : rien a repayer (le dossier passe alors a la reservation).
        if ($submission->payments()
            ->where('type', PaymentType::ScaleAudit)
            ->where('status', PaymentStatus::Succeeded)
            ->exists()
        ) {
            throw ScaleException::auditAlreadyPaid($submission->id);
        }

        // Reprise : reutiliser un checkout d'audit deja en cours plutot que d'en creer un second
        // (anti double-debit, crucial sur un double clic ou un moyen asynchrone).
        $existing = $this->existingAuditCheckout($submission);
        if ($existing !== null) {
            return $existing;
        }

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

    /** The in-flight audit checkout to reuse on resume, or null if none / not reusable. */
    private function existingAuditCheckout(Submission $submission): ?CheckoutSessionData
    {
        $payment = $submission->payments()
            ->where('type', PaymentType::ScaleAudit)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($payment === null || ! $this->gateways->has((string) $payment->provider)) {
            return null;
        }

        try {
            $url = $this->gateways->get((string) $payment->provider)->currentCheckoutUrl($payment);
        } catch (Throwable $e) {
            Log::warning('Could not reuse the SCALE audit checkout session, creating a new one.', ['exception' => $e]);

            return null;
        }

        return ($url !== null && $url !== '')
            ? new CheckoutSessionData((string) $payment->provider_reference, $url)
            : null;
    }
}
