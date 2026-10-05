<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Data\Payment\CheckoutSessionData;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Exceptions\Starter\StarterException;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Web\Starter\PackChangeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paiement du passage au Pro (SC12) : l'ecart de tarif au prorata des mois restants de l'annee, une fois le
 * mandat Pro signe. La confirmation (webhook, retour, reconciliation) applique la montee
 * (ApplyPackUpgradeAction). Anti double-debit : meme verrou que les autres paiements du dossier, et reprise
 * du checkout encore ouvert au meme montant.
 */
final readonly class StartPackUpgradePaymentAction
{
    public function __construct(
        private PackChangeService $packChanges,
        private PaymentGatewayRegistry $gateways,
    ) {}

    public function execute(Submission $submission, string $providerKey): CheckoutSessionData
    {
        return Cache::lock('checkout:'.$submission->getKey(), 15)->block(10, function () use ($submission, $providerKey): CheckoutSessionData {
            $upgrade = $this->packChanges->openUpgrade($submission)
                ?? throw StarterException::packUpgradeUnavailable($submission->id, 'no open upgrade');

            if ($upgrade->contract?->signature_status !== SignatureStatus::Signed) {
                throw StarterException::packUpgradeNotSigned($submission->id);
            }

            if ($submission->payments()->where('type', PaymentType::PackUpgrade)->where('status', PaymentStatus::Processing)->exists()) {
                throw StarterException::packUpgradeUnavailable($submission->id, 'an upgrade payment is awaiting confirmation');
            }

            $amount = $this->packChanges->upgradeQuoteCents();

            $existing = $this->existingCheckout($submission, $amount);
            if ($existing !== null) {
                return $existing;
            }

            if (! $this->gateways->has($providerKey)) {
                $providerKey = (string) array_key_first($this->gateways->options());
            }
            $gateway = $this->gateways->get($providerKey);

            $payment = $submission->payments()->create([
                'type' => PaymentType::PackUpgrade,
                'amount_cents' => $amount,
                'service_year' => (int) now()->year,
                'currency' => 'EUR',
                'provider' => $gateway->key(),
                'status' => PaymentStatus::Pending,
            ]);
            $upgrade->update(['payment_id' => $payment->id, 'amount_cents' => $amount]);

            $session = $gateway->createCheckout($payment);
            $payment->update(['provider_reference' => $session->providerReference]);

            return $session;
        });
    }

    /** Le checkout de montee encore ouvert au montant du jour (reprise), ou null. */
    private function existingCheckout(Submission $submission, int $amount): ?CheckoutSessionData
    {
        /** @var Payment|null $payment */
        $payment = $submission->payments()
            ->where('type', PaymentType::PackUpgrade)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($payment === null || $payment->amount_cents !== $amount || ! $this->gateways->has((string) $payment->provider)) {
            return null;
        }

        try {
            $url = $this->gateways->get((string) $payment->provider)->currentCheckoutUrl($payment);
        } catch (Throwable $e) {
            Log::warning('Could not reuse the pack upgrade checkout session, creating a new one.', ['exception' => $e]);

            return null;
        }

        return ($url !== null && $url !== '')
            ? new CheckoutSessionData((string) $payment->provider_reference, $url)
            : null;
    }
}
