<?php

declare(strict_types=1);

namespace App\Enums\Contract;

/**
 * Place d'un mandat dans la vie du dossier (SC12, changement de pack apres paiement). Un seul mandat en
 * vigueur par dossier ; celui d'un changement de pack en cours attend ; l'ancien est conserve comme trace.
 */
enum ContractRole: string
{
    case Current = 'current';
    case Pending = 'pending';
    case Superseded = 'superseded';

    /** Libelle back-office (francophone). */
    public function label(): string
    {
        return match ($this) {
            self::Current => __('En vigueur'),
            self::Pending => __('Changement de pack en cours'),
            self::Superseded => __('Remplacé'),
        };
    }
}
