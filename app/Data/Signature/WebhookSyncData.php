<?php

declare(strict_types=1);

namespace App\Data\Signature;

/**
 * Outcome of synchronising the signature provider webhook: the webhook ids now known for our callback
 * URL, whether one had to be created (none was registered yet, or recreate asked), and the ids of the
 * webhooks removed by a recreate.
 */
final readonly class WebhookSyncData
{
    /**
     * @param  list<string>  $ids
     * @param  list<string>  $deleted
     */
    public function __construct(
        public string $callbackUrl,
        public array $ids,
        public bool $created,
        public array $deleted = [],
    ) {}
}
