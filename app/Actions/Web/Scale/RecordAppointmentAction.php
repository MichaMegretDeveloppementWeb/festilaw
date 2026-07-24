<?php

declare(strict_types=1);

namespace App\Actions\Web\Scale;

use App\Enums\Appointment\AppointmentStatus;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Exceptions\Scale\ScaleException;
use App\Mail\FunnelNotification;
use App\Mail\ScaleConsultationBooked;
use App\Models\Appointment;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Records that a SCALE consultation has been requested (via the provided Google Calendar link)
 * and advances the submission to "in progress". Guards that the audit is paid first. The exact slot may
 * be filled in later by Festilaw from the back-office (no calendar webhook in scope).
 */
final readonly class RecordAppointmentAction
{
    public function __construct(private TeamNotifier $teamNotifier) {}

    public function execute(Submission $submission, ?string $googleEventReference = null): Appointment
    {
        // Un dossier annule ne prend pas de rendez-vous (garde au bord, comme le paiement de l'audit).
        if ($submission->status === SubmissionStatus::Cancelled) {
            throw ScaleException::dossierCancelled($submission->id);
        }

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

        try {
            $appointment = DB::transaction(function () use ($submission, $googleEventReference): Appointment {
                $appointment = $submission->appointment()->create([
                    'google_event_reference' => $googleEventReference,
                    'status' => AppointmentStatus::Requested,
                ]);

                // N'avance le statut que depuis un etat pre-terminal : ne retrograde jamais un dossier deja
                // Termine (prestation livree) ni ne reactive un Annule (deja bloque au-dessus).
                Submission::query()
                    ->whereKey($submission->id)
                    ->whereNotIn('status', [SubmissionStatus::Cancelled, SubmissionStatus::Completed])
                    ->update(['status' => SubmissionStatus::InProgress]);

                return $appointment;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Course : deux clics quasi simultanes ont franchi le test ci-dessus ; la contrainte unique
            // (submission_id) bloque le doublon. On retourne le rendez-vous cree par l'autre appel (qui
            // envoie deja les confirmations) plutot que de laisser remonter une erreur.
            return $submission->appointment()->first() ?? throw $e;
        }

        // Nouveau rendez-vous : confirmation au client ET notification a l'equipe. Effets de bord
        // peripheriques, hors transaction : un echec d'envoi est logue mais ne casse jamais la reservation.
        $this->sendConfirmations($submission);

        return $appointment;
    }

    /** Confirme la reservation au client et previent l'equipe Festilaw (best-effort, jamais bloquant). */
    private function sendConfirmations(Submission $submission): void
    {
        if ((string) $submission->email !== '') {
            try {
                Mail::to($submission->email)
                    ->locale($submission->locale ?: config('app.locale'))
                    ->send(new ScaleConsultationBooked($submission));
            } catch (Throwable $e) {
                Log::error('Failed to send the SCALE booking confirmation to the client.', [
                    'exception' => $e,
                    'submission' => $submission->id,
                ]);
            }
        }

        // TeamNotifier est deja resilient (try/catch + Log en interne).
        $this->teamNotifier->notify(new FunnelNotification($submission, FunnelNotificationReason::ConsultationBooked));
    }
}
