<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Data\Payment\CheckoutSessionData;
use App\Data\Payment\PaymentWebhookData;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Several payment providers can be active at the same time (the buyer chooses at checkout),
 * so they live in a Registry rather than behind a single config-selected driver. Each provider
 * is a Strategy behind this interface; adding one (e.g. PayPal) = a new class + config, nothing else.
 */
interface PaymentGatewayInterface
{
    /** Identifier of the provider (e.g. 'stripe', 'paypal', 'fake'). */
    public function key(): string;

    /** Human label shown at checkout (e.g. 'Card', 'PayPal'). */
    public function label(): string;

    /** Start a payment and return where to send the buyer. */
    public function createCheckout(Payment $payment): CheckoutSessionData;

    /** Verify + parse an incoming provider webhook. Throws on an invalid/untrusted payload. */
    public function parseWebhook(Request $request): PaymentWebhookData;
}
