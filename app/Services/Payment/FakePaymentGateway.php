<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Data\Payment\CheckoutSessionData;
use App\Data\Payment\PaymentWebhookData;
use App\Enums\Payment\PaymentEventOutcome;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Default provider: simulates a checkout with no external call, so the STARTER/SCALE payment
 * steps work end-to-end without any keys. Real providers (Stripe...) are enabled via config
 * once their credentials exist.
 */
final class FakePaymentGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return __('Test payment (dev)');
    }

    public function createCheckout(Payment $payment): CheckoutSessionData
    {
        return new CheckoutSessionData(
            providerReference: 'fake_'.Str::uuid()->toString(),
            redirectUrl: $this->devRedirectUrl($payment),
        );
    }

    /**
     * The Fake stands in for the provider's hosted checkout: it sends the buyer to the local
     * dev-completion route matching the payment's parcours. An explicit env override wins; only
     * STARTER has a journey screen for now, so other types fall back to the home page.
     */
    private function devRedirectUrl(Payment $payment): string
    {
        $configured = config('payment.fake.redirect_url');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $token = $payment->submission?->resume_token;

        return match (true) {
            $payment->type === PaymentType::StarterSubscription && $token !== null => route(
                'get-started.starter.dev-pay',
                ['dossier' => $token],
            ),
            default => url('/'),
        };
    }

    public function currentCheckoutUrl(Payment $payment): ?string
    {
        if ((string) ($payment->provider_reference ?? '') === '' || $payment->status === PaymentStatus::Succeeded) {
            return null;
        }

        return $this->devRedirectUrl($payment);
    }

    public function checkStatus(Payment $payment): PaymentWebhookData
    {
        return new PaymentWebhookData(
            providerReference: (string) ($payment->provider_reference ?? ''),
            outcome: $this->simulatedOutcome((string) ($payment->provider_reference ?? ''), $payment->status),
        );
    }

    /**
     * Dev/demo : une reference "sim:<issue>:..." laisse un paiement Fake rapporter un etat prestataire qui
     * DIVERGE de notre statut stocke (ex. paye chez le prestataire alors qu'on a note un echec), pour
     * exercer la reconciliation / le bouton "verifier chez le prestataire" sans vrai gateway. Sinon le
     * Fake reflete simplement notre statut (Succeeded => paye), comme il se completait via la route dev-pay.
     */
    private function simulatedOutcome(string $reference, PaymentStatus $status): PaymentEventOutcome
    {
        if (str_starts_with($reference, 'sim:')) {
            return match (explode(':', $reference)[1] ?? '') {
                'paid' => PaymentEventOutcome::Paid,
                'failed' => PaymentEventOutcome::Failed,
                'expired' => PaymentEventOutcome::Expired,
                'processing' => PaymentEventOutcome::Processing,
                default => PaymentEventOutcome::Unresolved,
            };
        }

        return $status === PaymentStatus::Succeeded ? PaymentEventOutcome::Paid : PaymentEventOutcome::Unresolved;
    }

    public function parseWebhook(Request $request): PaymentWebhookData
    {
        // Dev: no signature to verify; read the reference (and optional outcome) from the payload.
        return new PaymentWebhookData(
            providerReference: (string) $request->input('provider_reference', ''),
            outcome: $this->fakeOutcome($request),
            clientReference: $request->input('client_reference') !== null ? (string) $request->input('client_reference') : null,
        );
    }

    /** Dev payload → outcome: `outcome=paid|failed|processing|expired|refunded`, or the legacy paid/failed booleans. */
    private function fakeOutcome(Request $request): PaymentEventOutcome
    {
        if ($request->filled('outcome')) {
            return match ((string) $request->input('outcome')) {
                'failed' => PaymentEventOutcome::Failed,
                'processing' => PaymentEventOutcome::Processing,
                'expired' => PaymentEventOutcome::Expired,
                'refunded' => PaymentEventOutcome::Refunded,
                'unresolved' => PaymentEventOutcome::Unresolved,
                default => PaymentEventOutcome::Paid,
            };
        }

        if ($request->boolean('failed')) {
            return PaymentEventOutcome::Failed;
        }

        return $request->boolean('paid', true) ? PaymentEventOutcome::Paid : PaymentEventOutcome::Unresolved;
    }
}
