<?php

declare(strict_types=1);

namespace App\Data\Payment;

/**
 * Provider-agnostic result of parsing a payment webhook.
 */
final readonly class PaymentWebhookData
{
    public function __construct(
        public string $providerReference,
        public bool $paid,
    ) {}
}
