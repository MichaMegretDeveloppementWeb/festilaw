<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use Carbon\CarbonImmutable;

/**
 * Ce que la tache planifiee a decide a ce passage : rien (generation eteinte, ou aucun creneau du, avec le moment
 * du prochain lot), un lot lance, ou un lot du mais retenu par le plafond de brouillons en attente.
 */
final readonly class ScheduledGenerationPlan
{
    private function __construct(
        public bool $enabled,
        public ?CarbonImmutable $nextLaunchAt,
        public int $launched,
        public ?BlogGenerationOutcomeReason $blockedBy,
    ) {}

    public static function disabled(): self
    {
        return new self(false, null, 0, null);
    }

    public static function notDue(CarbonImmutable $nextLaunchAt): self
    {
        return new self(true, $nextLaunchAt, 0, null);
    }

    public static function launched(int $count): self
    {
        return new self(true, null, $count, null);
    }

    public static function blocked(BlogGenerationOutcomeReason $reason): self
    {
        return new self(true, null, 0, $reason);
    }
}
