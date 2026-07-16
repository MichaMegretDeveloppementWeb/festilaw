<?php

declare(strict_types=1);

namespace App\Enums\Submission;

enum SubmissionType: string
{
    case Contact = 'contact';
    case Starter = 'starter';
    case Pro = 'pro';
    case Scale = 'scale';

    /** Libelle lisible du parcours (noms de packs, back-office). */
    public function label(): string
    {
        return match ($this) {
            self::Contact => __('Contact'),
            self::Starter => 'Creator Pack',
            self::Pro => 'Pro Pack',
            self::Scale => 'Scale Pack',
        };
    }
}
