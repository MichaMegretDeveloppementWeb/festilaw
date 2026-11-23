<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation\Health;

use Carbon\CarbonImmutable;

/**
 * Une cle OpenRouter acceptee : sa limite et ce qu'il en reste, en dollars (nulles sans limite), et sa date
 * d'expiration (nulle si elle n'expire pas).
 */
final readonly class OpenRouterKeyStatus
{
    public function __construct(
        public ?float $limit,
        public ?float $remaining,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
