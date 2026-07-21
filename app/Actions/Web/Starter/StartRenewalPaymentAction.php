<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Data\Payment\CheckoutSessionData;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;
use App\Services\Billing\RenewalService;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        // Garde defensive : aucun paiement (meme un renouvellement) pour un dossier qui n'a pas complete
        // sa mise en place initiale -> le mandat DOIT etre signe. En flux normal l'annee 1 l'exige deja
        // (StartStarterPaymentAction), mais on ne fait pas confiance a cet invariant implicite : on le
        // verifie explicitement au bord. Les documents sont propres a l'annee 1, non re-exiges ici.
        if ($submission->contract()->where('signature_status', SignatureStatus::Signed)->doesntExist()) {
            throw StarterException::dossierIncomplete($submission->id);
        }

        // Reprise : un paiement de renouvellement deja en cours -> reutiliser sa session plutot que d'en
        // creer une 2e (anti double-debit, crucial notamment sur un double clic).
        $existing = $this->existingRenewalCheckout($submission);
        if ($existing !== null) {
            return $existing;
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

    /** La session de renouvellement deja en cours pour ce dossier (reprise), ou null si aucune / non reutilisable. */
    private function existingRenewalCheckout(Submission $submission): ?CheckoutSessionData
    {
        $payment = $submission->payments()
            ->where('type', PaymentType::AnnualRenewal)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($payment === null || ! $this->gateways->has((string) $payment->provider)) {
            return null;
        }

        try {
            $url = $this->gateways->get((string) $payment->provider)->currentCheckoutUrl($payment);
        } catch (Throwable $e) {
            Log::warning('Could not reuse the renewal checkout session, creating a new one.', ['exception' => $e]);

            return null;
        }

        return ($url !== null && $url !== '')
            ? new CheckoutSessionData((string) $payment->provider_reference, $url)
            : null;
    }
}
