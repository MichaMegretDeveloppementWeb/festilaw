<?php

declare(strict_types=1);

namespace App\Data\System;

use Carbon\CarbonImmutable;

/** Les erreurs du journal du serveur sur une periode, regroupees, les plus frequentes d'abord. */
final readonly class ErrorLogSummary
{
    /**
     * @param  list<ErrorLogGroup>  $groups
     * @param  list<string>  $unreadableFiles  les fichiers du journal qui n'ont pas pu etre lus
     */
    public function __construct(
        public CarbonImmutable $since,
        public CarbonImmutable $until,
        public array $groups,
        public array $unreadableFiles = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->groups === [] && $this->unreadableFiles === [];
    }

    public function occurrences(): int
    {
        return array_sum(array_map(static fn (ErrorLogGroup $group): int => $group->count, $this->groups));
    }
}
