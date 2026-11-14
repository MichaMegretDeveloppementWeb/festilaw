<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Statut d'une etape d'une generation. Une etape restee `Running` apres une coupure du worker est reprise
 * au passage suivant : son essai en cours est compte. Colonne sous contrainte CHECK en production
 * (cf. BlogGenerationStatus).
 */
enum BlogGenerationStepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('À faire'),
            self::Running => __('En cours'),
            self::Completed => __('Terminée'),
            self::Failed => __('En échec'),
        };
    }
}
