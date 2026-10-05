<?php

declare(strict_types=1);

namespace App\Data\Payment;

/**
 * Result of a Stripe webhook endpoint sync: the endpoint found for our URL (null if none), whether it
 * already listens to every event ("*"), and the events added (or that would be added on a dry run).
 */
final readonly class StripeWebhookSyncData
{
    /** @param  list<string>  $addedEvents */
    public function __construct(
        public ?string $endpointId,
        public bool $listensToAll,
        public array $addedEvents,
    ) {}
}
