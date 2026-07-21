<?php

declare(strict_types=1);

namespace App\Enums\Contract;

enum SignatureStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Declined = 'declined';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('En attente de signature'),
            self::Signed => __('Signé'),
            self::Declined => __('Refusé'),
            self::Expired => __('Expiré'),
        };
    }

    /**
     * States from which a signature outcome may still be recorded — the only source of a Signed/Declined/
     * Expired transition. A restart (new signing session) resets a Declined/Expired contract to Pending.
     *
     * @return array<int, self>
     */
    public static function confirmable(): array
    {
        return [self::Pending];
    }
}
