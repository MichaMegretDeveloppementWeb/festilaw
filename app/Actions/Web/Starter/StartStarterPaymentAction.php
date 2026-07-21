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

        $gateway = $this->gateways->get($providerKey);

        // Ecriture unique : pas de transaction (cf. architecture-couches, pragmatisme).
        // Annee 1 au prorata (date de signature -> 31/12), cf. contrat. La reprise annuelle plein tarif
        // sera geree separement (rappel + paiement depuis le dossier). Le tarif depend du pack (type).
        $payment = $submission->payments()->create([
            'type' => PaymentType::StarterSubscription,
            'amount_cents' => $this->prorator->firstYearCents(
                $submission->type->annualCents(),
                $submission->contract?->signed_at ?? now(),
            ),
            'currency' => 'EUR',
            'provider' => $gateway->key(),
            'status' => PaymentStatus::Pending,
        ]);

        // La reference provider est stockee apres l'appel externe. En cas d'echec de cet update
        // (rare), le paiement reste Pending et est reconcilie manuellement au back-office ; l'integration
        // Stripe reelle passera aussi notre payment id en metadata pour un rapprochement robuste.
        $session = $gateway->createCheckout($payment);
        $payment->update(['provider_reference' => $session->providerReference]);

        return $session;
    }
}
