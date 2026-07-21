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

    public function execute(Payment $payment, ?string $providerReference = null): Payment
    {
        $processed = DB::transaction(function () use ($payment, $providerReference): bool {
            // Update conditionnel atomique : seule la 1re livraison concurrente affecte une ligne, et
            // seuls les etats confirmables transitionnent (un Refunded/Failed/Expired n'est jamais ecrase).
            $affected = Payment::query()
                ->whereKey($payment->getKey())
                ->whereIn('status', PaymentStatus::confirmable())
                ->update([
                    'status' => PaymentStatus::Succeeded,
                    'provider_reference' => $providerReference ?? $payment->provider_reference,
                    'paid_at' => now(),
                ]);

            if ($affected === 0) {
                return false;
            }

            // Le dossier devient l'espace "mon dossier" du client : son lien de reprise ne doit plus expirer.
            $payment->submission()->update([
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
