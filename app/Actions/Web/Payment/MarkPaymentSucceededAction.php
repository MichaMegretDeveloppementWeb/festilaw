<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Mail\FunnelNotification;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Records a successful payment (called by the Stripe webhook), whatever the parcours
 * (STARTER subscription or SCALE audit). Idempotent AND concurrency-safe: only the first of
 * two redelivered webhooks transitions the state and notifies. Advances the submission to "paid".
 */
final readonly class MarkPaymentSucceededAction
{
    public function execute(Payment $payment, ?string $providerReference = null): Payment
    {
        $processed = DB::transaction(function () use ($payment, $providerReference): bool {
            // Update conditionnel atomique : seule la 1re livraison concurrente affecte une ligne.
            $affected = Payment::query()
                ->whereKey($payment->getKey())
                ->where('status', '!=', PaymentStatus::Succeeded)
                ->update([
                    'status' => PaymentStatus::Succeeded,
                    'provider_reference' => $providerReference ?? $payment->provider_reference,
                    'paid_at' => now(),
                ]);

            if ($affected === 0) {
                return false;
            }

            $payment->submission()->update(['status' => SubmissionStatus::Paid]);

            return true;
        });

        $payment->refresh();

        if ($processed) {
            // Notification synchrone a Festilaw, apres commit (une seule fois).
            Mail::to(config('festilaw.notification_email'))
                ->send(new FunnelNotification($payment->submission, FunnelNotificationReason::PaymentReceived));
        }

        return $payment;
    }
}
