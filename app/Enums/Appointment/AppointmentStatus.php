<?php

declare(strict_types=1);

namespace App\Enums\Appointment;

enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Libelle back-office (francophone). */
    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Demandé'),
            self::Scheduled => __('Programmé'),
            self::Completed => __('Terminé'),
            self::Cancelled => __('Annulé'),
        };
    }

    /**
     * Libelle affiche au client dans son espace Scale : cle anglaise traduite FR/ES, phrase complete pour
     * accorder le genre ("consultation", "consulta") sans entrer en collision avec d'autres cles.
     */
    public function clientLabel(): string
    {
        return match ($this) {
            self::Requested => __('Consultation requested'),
            self::Scheduled => __('Consultation scheduled'),
            self::Completed => __('Consultation completed'),
            self::Cancelled => __('Consultation cancelled'),
        };
    }
}
