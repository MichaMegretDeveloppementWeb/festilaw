<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Data\Payment\CheckoutSessionData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Exceptions\Starter\StarterException;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Support\Facades\DB;

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
    ) {}

    public function execute(Submission $submission, string $providerKey): CheckoutSessionData
    {
        $submission->loadMissing(['contract', 'uploadedDocuments']);

        if (! $this->resolver->resolve($submission)->isComplete) {
            throw StarterException::dossierIncomplete($submission->id);
        }

        $gateway = $this->gateways->get($providerKey);

        $payment = DB::transaction(fn (): Payment => $submission->payments()->create([
            'type' => PaymentType::StarterSubscription,
            'amount_cents' => (int) config('festilaw.starter.amount_cents'),
            'currency' => 'EUR',
            'provider' => $gateway->key(),
            'status' => PaymentStatus::Pending,
        ]));

        $session = $gateway->createCheckout($payment);

        $payment->update(['provider_reference' => $session->providerReference]);

        return $session;
    }
}
