<?php

declare(strict_types=1);

namespace App\Data\Signature;

/**
 * Outcome of synchronising the signature provider webhook: the webhook ids now known for our callback
 * URL, and whether one had to be created (none was registered yet).
 */
final readonly class WebhookSyncData
{
    /** @param  list<string>  $ids */
    public function __construct(
        public string $callbackUrl,
        public array $ids,
        public bool $created,
    ) {}
}
