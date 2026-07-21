<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Etat de renouvellement d'un dossier actif, derive des paiements (aucun statut stocke) :
 * a jour, a renouveler (dans la fenetre de grace) ou en retard (grace depassee sans paiement).
 */
enum RenewalStatus: string
{
    case UpToDate = 'up_to_date';
    case Due = 'due';
    case Overdue = 'overdue';

    /** Libelle back-office (francophone). */
    public function label(): string
    {
        return match ($this) {
            self::UpToDate => __('À jour'),
            self::Due => __('À renouveler'),
            self::Overdue => __('En retard'),
        };
    }
}
