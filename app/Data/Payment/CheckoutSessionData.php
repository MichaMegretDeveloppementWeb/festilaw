<?php

declare(strict_types=1);

namespace App\Data\Payment;

/**
 * Provider-agnostic output of a checkout: no Stripe/PayPal object ever leaks upward.
 */
final readonly class CheckoutSessionData
{
    public function __construct(
        public string $providerReference,
        public string $redirectUrl,
    ) {}
}
