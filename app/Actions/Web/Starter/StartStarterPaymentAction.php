<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Data\Payment\CheckoutSessionData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;
use App\Services\Billing\AnnualFeeProrator;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Starts the STARTER subscription payment. Guards on the dossier being complete (invariant metier
 * -> exception typee, pas un 422), then creates a pending Payment and a checkout with the chosen
 * provider. Confirmation arrives later via the payment webhook (MarkPaymentSucceededAction).
 */
final readonly class StartStarterPaymentAction
{
    public function __construct(
        private StarterDossierResolver $resolver,
        private PaymentGatewayRegistry $gateways,
        private AnnualFeeProrator $prorator,
    ) {}

    public function execute(Submission $submission, string $providerKey): CheckoutSessionData
    {
        // load() (pas loadMissing) : force le rafraichissement des relations, meme si deja chargees.
        $submission->load(['contract', 'uploadedDocuments']);

        if (! $this->resolver->resolve($submission)->isComplete) {
            throw StarterException::dossierIncomplete($submission->id);
        }

        // Verrou anti double-debit : serialise deux "Payer" concurrents (double clic / deux onglets) pour ce
        // dossier. Le verrou (table cache_locks) ne tient AUCUNE transaction DB pendant l'appel HTTP au
        // prestataire ; il serialise seulement les demarrages. 15s de bail, 10s d'attente.
        return Cache::lock('checkout:'.$submission->getKey(), 15)->block(10, function () use ($submission, $providerKey): CheckoutSessionData {
            // Reprise : un checkout d'abonnement deja en cours (Pending) -> reutiliser sa session plutot
            // que d'en creer une 2e (anti double-debit, crucial notamment sur un double clic).
            $existing = $this->existingCheckout($submission);
            if ($existing !== null) {
                return $existing;
            }

            // Garde : un provider inconnu (l'entree vient du POST, potentiellement forgee) retombe sur le
            // provider par defaut plutot que de lever une erreur opaque.
            if (! $this->gateways->has($providerKey)) {
                $providerKey = (string) array_key_first($this->gateways->options());
            }
            $gateway = $this->gateways->get($providerKey);

            // Annee 1 au prorata (date de signature -> 31/12), cf. contrat. La reprise annuelle plein tarif
            // est geree separement (rappel + paiement depuis le dossier). Le tarif depend du pack (type).
            $reference = $submission->contract?->signed_at ?? now();
            $payment = $submission->payments()->create([
                'type' => PaymentType::StarterSubscription,
                'amount_cents' => $this->prorator->firstYearCents($submission->type->annualCents(), $reference),
                'service_year' => (int) $reference->year,
                'currency' => 'EUR',
                'provider' => $gateway->key(),
                'status' => PaymentStatus::Pending,
            ]);

            // La reference provider est stockee apres l'appel externe. En cas d'echec de cet update
            // (rare), le paiement reste Pending et est reconcilie manuellement au back-office ; l'integration
            // Stripe reelle passe aussi notre payment id en metadata pour un rapprochement robuste.
            $session = $gateway->createCheckout($payment);
            $payment->update(['provider_reference' => $session->providerReference]);

            return $session;
        });
    }

    /** Le checkout d'abonnement deja en cours pour ce dossier (reprise / double clic), ou null si aucun / non reutilisable. */
    private function existingCheckout(Submission $submission): ?CheckoutSessionData
    {
        $payment = $submission->payments()
            ->where('type', PaymentType::StarterSubscription)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($payment === null || ! $this->gateways->has((string) $payment->provider)) {
            return null;
        }

        try {
            $url = $this->gateways->get((string) $payment->provider)->currentCheckoutUrl($payment);
        } catch (Throwable $e) {
            Log::warning('Could not reuse the STARTER checkout session, creating a new one.', ['exception' => $e]);

            return null;
        }

        return ($url !== null && $url !== '')
            ? new CheckoutSessionData((string) $payment->provider_reference, $url)
            : null;
    }
}
