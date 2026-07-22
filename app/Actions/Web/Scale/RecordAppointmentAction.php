<?php

declare(strict_types=1);

namespace App\Actions\Web\Scale;

use App\Enums\Appointment\AppointmentStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Exceptions\Scale\ScaleException;
use App\Models\Appointment;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;

/**
 * Records that a SCALE consultation has been requested (via the provided Google Calendar link)
 * and advances the submission to "in progress". Guards that the audit is paid first. The exact slot may
 * be filled in later by Festilaw from the back-office (no calendar webhook in scope).
 */
final readonly class RecordAppointmentAction
{
    public function execute(Submission $submission, ?string $googleEventReference = null): Appointment
    {
        // Reserver n'a de sens qu'une fois l'audit paye (garde metier au bord).
        if ($submission->payments()
            ->where('type', PaymentType::ScaleAudit)
            ->where('status', PaymentStatus::Succeeded)
            ->doesntExist()
        ) {
            throw ScaleException::auditNotPaid($submission->id);
        }

        // Idempotent : un dossier n'a qu'un rendez-vous (unique submission_id, cf. chantier #4). Un second
        // clic sur "j'ai reserve" ne cree pas de doublon, il retourne le rendez-vous existant.
        $existing = $submission->appointment()->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($submission, $googleEventReference): Appointment {
            $appointment = $submission->appointment()->create([
                'google_event_reference' => $googleEventReference,
                'status' => AppointmentStatus::Requested,
            ]);

            $submission->update(['status' => SubmissionStatus::InProgress]);

            return $appointment;
        });
    }
}
