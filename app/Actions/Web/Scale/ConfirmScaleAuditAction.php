<?php

declare(strict_types=1);

namespace App\Actions\Web\Scale;

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Confirms a SCALE audit payment on the client's return from the checkout: queries the provider for each
 * pending audit payment and marks the paid ones succeeded. The signed webhook remains the server-side
 * source of truth (in production); this poll-on-return covers local dev (where the webhook can't reach the
 * site) and gives instant feedback. Non-blocking: any error is logged, never surfaced to the client.
 */
final readonly class ConfirmScaleAuditAction
{
    public function __construct(
        private PaymentGatewayRegistry $gateways,
        private MarkPaymentSucceededAction $markPaymentSucceeded,
    ) {}

    public function execute(Submission $submission): void
    {
        $pending = $submission->payments()
            ->where('type', PaymentType::ScaleAudit)
            ->whereIn('status', PaymentStatus::confirmable())
            ->get();

        foreach ($pending as $payment) {
            try {
                $event = $this->gateways->get((string) $payment->provider)->checkStatus($payment);

                if ($event->isPaid()) {
                    $this->markPaymentSucceeded->execute($payment, $event->providerReference);
                }
            } catch (Throwable $e) {
                Log::channel('payments')->error('SCALE audit confirm-on-return failed.', [
                    'exception' => $e,
                    'submission' => $submission->id,
                    'payment' => $payment->id,
                ]);
            }
        }
    }
}
