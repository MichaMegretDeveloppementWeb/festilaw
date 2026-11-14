<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Ce qui a declenche une generation : une personne au back-office, ou la tache planifiee. Colonne sous
 * contrainte CHECK en production (cf. BlogGenerationStatus).
 */
enum BlogGenerationTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Manuel'),
            self::Scheduled => __('Automatique'),
        };
    }
}
