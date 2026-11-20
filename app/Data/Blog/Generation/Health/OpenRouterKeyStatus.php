<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation\Health;

/** La cle OpenRouter du site, acceptee : sa limite et ce qu'il en reste, en dollars (nulles sans limite). */
final readonly class OpenRouterKeyStatus
{
    public function __construct(
        public ?float $limit,
        public ?float $remaining,
    ) {}
}
