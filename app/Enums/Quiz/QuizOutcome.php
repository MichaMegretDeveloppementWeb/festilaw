<?php

declare(strict_types=1);

namespace App\Enums\Quiz;

enum QuizOutcome: string
{
    case Concerned = 'concerned';
    case Excluded = 'excluded';
    case NotConcerned = 'not_concerned';

    /**
     * Issue derivee des trois reponses du quiz (source de verite cote serveur, on ne fait pas
     * confiance au calcul du client) : vend une categorie exclue => exclu ; hors UE ET vend dans
     * l'UE => concerne ; sinon => non concerne.
     */
    public static function fromAnswers(bool $basedOutsideEu, bool $sellsToEu, bool $sellsRestricted): self
    {
        return match (true) {
            $sellsRestricted => self::Excluded,
            $basedOutsideEu && $sellsToEu => self::Concerned,
            default => self::NotConcerned,
        };
    }

    /** Libelle francais (affiche uniquement dans le back-office interne). */
    public function label(): string
    {
        return match ($this) {
            self::Concerned => __('Concerné'),
            self::Excluded => __('Catégorie exclue'),
            self::NotConcerned => __('Non concerné'),
        };
    }
}
