<?php

declare(strict_types=1);

namespace App\Enums\Contract;

enum SignatureStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('En attente de signature'),
            self::Signed => __('Signé'),
            self::Declined => __('Refusé'),
        };
    }
}
