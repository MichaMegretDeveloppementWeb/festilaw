<?php

declare(strict_types=1);

namespace App\Enums\Quiz;

enum QuizOutcome: string
{
    case Concerned = 'concerned';
    case Excluded = 'excluded';
    case NotConcerned = 'not_concerned';

    /** Issue derivee des trois reponses, cote serveur (le calcul du client n'est pas fiable). */
    public static function fromAnswers(bool $basedOutsideEu, bool $sellsToEu, bool $sellsRestricted): self
    {
        return match (true) {
            $sellsRestricted => self::Excluded,
            $basedOutsideEu && $sellsToEu => self::Concerned,
            default => self::NotConcerned,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Concerned => __('Concerné'),
            self::Excluded => __('Catégorie exclue'),
            self::NotConcerned => __('Non concerné'),
        };
    }
}
