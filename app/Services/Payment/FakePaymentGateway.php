<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Data\Payment\CheckoutSessionData;
use App\Data\Payment\PaymentWebhookData;
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
        return 'Test payment (dev)';
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
                ['locale' => app()->getLocale(), 'dossier' => $token],
            ),
            default => url('/'),
        };
    }

    public function parseWebhook(Request $request): PaymentWebhookData
    {
        // Dev: no signature to verify; read the reference (and optional outcome) from the payload.
        return new PaymentWebhookData(
            providerReference: (string) $request->input('provider_reference', ''),
            paid: $request->boolean('paid', true),
        );
    }
}
