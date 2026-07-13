<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Data\Payment\CheckoutSessionData;
use App\Models\Payment;
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
            redirectUrl: (string) (config('payment.fake.redirect_url') ?? url('/')),
        );
    }
}
