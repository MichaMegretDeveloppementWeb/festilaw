<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Appointment\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonInterface;

/**
 * Met a jour un rendez-vous SCALE depuis le back-office : Festilaw renseigne le creneau confirme
 * (scheduled_at) et fait avancer le statut (Demande -> Programme -> Termine / Annule). Aucun webhook
 * agenda Google (hors perimetre) : la confirmation du creneau est manuelle.
 */
final readonly class UpdateAppointmentAction
{
    public function execute(Appointment $appointment, ?CarbonInterface $scheduledAt, AppointmentStatus $status): void
    {
        $appointment->update([
            'scheduled_at' => $scheduledAt,
            'status' => $status,
        ]);
    }
}
