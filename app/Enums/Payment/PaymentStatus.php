<?php

declare(strict_types=1);

namespace App\Enums\Payment;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('En attente'),
            self::Processing => __('En cours de traitement'),
            self::Succeeded => __('Réussi'),
            self::Failed => __('Échoué'),
            self::Expired => __('Expiré'),
            self::Refunded => __('Remboursé'),
        };
    }

    /**
     * States from which the payment can still become paid — the only states an incoming "succeeded"
     * event may transition. Guards the state machine against a late event overwriting a terminal state
     * (a Refunded payment must never flip back to Succeeded).
     *
     * @return array<int, self>
     */
    public static function confirmable(): array
    {
        return [self::Pending, self::Processing];
    }

    /** Terminal states: the automatic pipeline performs no further transition (except Succeeded → Refunded). */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Pending, self::Processing => false,
            self::Succeeded, self::Failed, self::Expired, self::Refunded => true,
        };
    }
}
