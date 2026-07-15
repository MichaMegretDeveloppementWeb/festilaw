<?php

declare(strict_types=1);

namespace App\Data\Payment;

/**
 * Provider-agnostic result of parsing a payment webhook (or a status poll).
 *
 * `clientReference` carries our own Payment id (Stripe client_reference_id / metadata) so the webhook
 * can be reconciled even if the provider reference was never stored · match by provider ref OR our id.
 */
final readonly class PaymentWebhookData
{
    public function __construct(
        public string $providerReference,
        public bool $paid,
        public bool $failed = false,
        public ?string $clientReference = null,
    ) {}
}
