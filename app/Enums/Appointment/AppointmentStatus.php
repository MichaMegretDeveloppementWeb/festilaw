<?php

declare(strict_types=1);

namespace App\Enums\Appointment;

enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Demandé'),
            self::Scheduled => __('Programmé'),
            self::Completed => __('Terminé'),
            self::Cancelled => __('Annulé'),
        };
    }
}
