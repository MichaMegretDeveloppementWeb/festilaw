<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Mail\FunnelNotification;
use App\Mail\StarterPaymentConfirmed;
use App\Models\Payment;
use App\Services\Notification\TeamNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Records a successful payment (called by the Stripe webhook), whatever the parcours
 * (STARTER subscription or SCALE audit). Idempotent AND concurrency-safe: only the first of
 * two redelivered webhooks transitions the state and notifies. Advances the submission to "paid".
 */
final readonly class MarkPaymentSucceededAction
{
    public function __construct(private TeamNotifier $teamNotifier) {}

    /**
     * Nominal confirmation (webhook, poll-on-return, cron de reconciliation) : conservateur, ne
     * transitionne que depuis un etat confirmable (Pending/Processing).
     */
    public function execute(Payment $payment, ?string $providerReference = null): Payment
    {
        return $this->confirm($payment, $providerReference, PaymentStatus::confirmable());
    }

    /**
     * Re-interrogation DELIBEREE de la source de verite (bouton "verifier chez Stripe" sur un paiement
     * echoue) : si le provider dit "paye", on corrige une fausse-echec. Autorise donc aussi Failed/Expired
     * -> Succeeded, contrairement au chemin automatique. Jamais depuis Succeeded/Refunded (deja regles).
     */
    public function reconcile(Payment $payment, ?string $providerReference = null): Payment
    {
        return $this->confirm($payment, $providerReference, [
            PaymentStatus::Pending,
            PaymentStatus::Processing,
            PaymentStatus::Failed,
            PaymentStatus::Expired,
        ]);
    }

    /**
     * @param  array<int, PaymentStatus>  $fromStatuses
     */
    private function confirm(Payment $payment, ?string $providerReference, array $fromStatuses): Payment
    {
        $processed = DB::transaction(function () use ($payment, $providerReference, $fromStatuses): bool {
            // Update conditionnel atomique : seule la 1re livraison concurrente affecte une ligne, et
            // seuls les etats sources autorises transitionnent (un Succeeded/Refunded n'est jamais ecrase).
            $affected = Payment::query()
                ->whereKey($payment->getKey())
                ->whereIn('status', $fromStatuses)
                ->update([
                    'status' => PaymentStatus::Succeeded,
                    'provider_reference' => $providerReference ?? $payment->provider_reference,
                    'paid_at' => now(),
                ]);

            if ($affected === 0) {
                return false;
            }

            // Le dossier devient l'espace "mon dossier" du client : son lien de reprise ne doit plus expirer.
            // On n'avance vers "Paye" que depuis un etat pre-actif : jamais reactiver un dossier Annule
            // (reactivation silencieuse par un webhook tardif) ni retrograder un dossier Termine (Termine
            // et renouvellement sont orthogonaux, cf. chantier #1). Un changement de statut sur un dossier
            // annule ne passe que par le menu admin, jamais par ce chemin automatique.
            $payment->submission()
                ->whereNotIn('status', [SubmissionStatus::Cancelled, SubmissionStatus::Completed])
                ->update([
                    'status' => SubmissionStatus::Paid,
                    'resume_expires_at' => null,
                ]);

            return true;
        });

        $payment->refresh();

        if ($processed) {
            // Notification synchrone a Festilaw, apres commit (une seule fois) ; un echec est logue
            // sans casser la confirmation (important pour le webhook, qui doit repondre 200).
            $this->teamNotifier->notify(new FunnelNotification($payment->submission, FunnelNotificationReason::PaymentReceived));
            $this->emailBuyerConfirmation($payment);
        }

        return $payment;
    }

    /**
     * Confirmation email to the buyer (subscription payments: year 1 AND renewals) · also the safety
     * net for slow async payments. Peripheral side effect: a failure is logged but never breaks the
     * confirmation.
     */
    private function emailBuyerConfirmation(Payment $payment): void
    {
        if (! $payment->type->isSubscription()) {
            return;
        }

        $submission = $payment->submission;
        if ($submission === null || (string) $submission->email === '') {
            return;
        }

        // Dossier reste Annule (le garde ci-dessus ne l'a pas reactive) : ne pas confirmer au client
        // un service qui n'est pas actif. L'equipe est tout de meme notifiee (argent recu = actionnable).
        if ($submission->status === SubmissionStatus::Cancelled) {
            return;
        }

        try {
            Mail::to($submission->email)
                ->locale($submission->locale ?: config('app.locale'))
                ->send(new StarterPaymentConfirmed($submission));
        } catch (Throwable $e) {
            Log::error('Failed to send the STARTER payment confirmation email.', [
                'exception' => $e,
                'submission' => $submission->id,
            ]);
        }
    }
}
