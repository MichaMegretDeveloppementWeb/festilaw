<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Mail\FunnelNotification;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Records a successful payment (called by the Stripe webhook), whatever the parcours
 * (STARTER subscription or SCALE audit). Idempotent: a re-delivered webhook is a no-op.
 * Advances the submission to "paid" and notifies Festilaw synchronously.
 */
final readonly class MarkPaymentSucceededAction
{
    public function execute(Payment $payment, ?string $providerReference = null): Payment
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return $payment;
        }

        DB::transaction(function () use ($payment, $providerReference): void {
            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'provider_reference' => $providerReference ?? $payment->provider_reference,
                'paid_at' => now(),
            ]);

            $payment->submission()->update(['status' => SubmissionStatus::Paid]);
        });

        // Notification synchrone a Festilaw, apres commit.
        Mail::to(config('festilaw.notification_email'))
            ->send(new FunnelNotification($payment->submission()->first(), 'Payment received'));

        return $payment->refresh();
    }
}
