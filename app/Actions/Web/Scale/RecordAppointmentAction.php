<?php

declare(strict_types=1);

namespace App\Actions\Web\Scale;

use App\Enums\Appointment\AppointmentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Appointment;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;

/**
 * Records that a SCALE consultation has been requested (via the provided Google Calendar link)
 * and advances the submission to "in progress". The exact slot may be filled in later by Festilaw
 * from the back-office (no calendar webhook in scope).
 */
final readonly class RecordAppointmentAction
{
    public function execute(Submission $submission, ?string $googleEventReference = null): Appointment
    {
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
