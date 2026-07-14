<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Data\Payment\CheckoutSessionData;
use App\Data\Payment\PaymentWebhookEvent;
use App\Exceptions\Payment\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Adapter for Stripe Checkout. Structure is in place; only the real API calls remain, to be added
 * when credentials are provided (PAYMENT_PROVIDERS=stripe + STRIPE_* env). Payment confirmation
 * comes from the Stripe webhook (source of truth), not the redirect.
 *
 * Until then the enabled set defaults to 'fake', so this class is never invoked in dev/test.
 */
final class StripePaymentGateway implements PaymentGatewayInterface
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config) {}

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Card';
    }

    public function createCheckout(Payment $payment): CheckoutSessionData
    {
        if (empty($this->config['secret_key'])) {
            throw PaymentException::providerNotConfigured('stripe');
        }

        // TODO: real Stripe Checkout Session (amount = $payment->amount_cents, currency, success/cancel URLs),
        // return the session id + hosted checkout URL. Upstream consumes CheckoutSessionData unchanged.
        throw PaymentException::providerNotConfigured('stripe');
    }

    public function parseWebhook(Request $request): PaymentWebhookEvent
    {
        if (empty($this->config['webhook_secret'])) {
            throw PaymentException::providerNotConfigured('stripe');
        }

        // TODO: verify the Stripe-Signature header with the webhook secret, then map
        // checkout.session.completed / payment_intent.succeeded to a PaymentWebhookEvent.
        throw PaymentException::providerNotConfigured('stripe');
    }
}
