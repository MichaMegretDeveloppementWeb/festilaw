<?php

declare(strict_types=1);

namespace App\Enums\Payment;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /** Libelle francais (affiche uniquement dans le back-office interne). */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('En attente'),
            self::Succeeded => __('Réussi'),
            self::Failed => __('Échoué'),
            self::Refunded => __('Remboursé'),
        };
    }
}
