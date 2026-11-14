<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Statut d'une generation. ATTENTION : la colonne blog_generations.status porte une contrainte CHECK en
 * production ; ajouter un cas ici exige une migration de rafraichissement de la contrainte.
 *
 * `Abandoned` : arretee sans erreur et sans article (aucun sujet pertinent, plafond atteint, lot non lance).
 * `Failed` : une etape a echoue apres tous ses essais. `Cancelled` : arretee a la demande d'une personne.
 */
enum BlogGenerationStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('En attente'),
            self::Running => __('En cours'),
            self::Completed => __('Terminée'),
            self::Abandoned => __('Abandonnée'),
            self::Failed => __('En échec'),
            self::Cancelled => __('Arrêtée'),
        };
    }

    /** Plus rien ne se passera sur cette generation sans une relance. */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Queued, self::Running => false,
            self::Completed, self::Abandoned, self::Failed, self::Cancelled => true,
        };
    }

    /**
     * Valeurs des statuts qui restent a traiter par le worker.
     *
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Queued->value, self::Running->value];
    }
}
