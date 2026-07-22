<?php

declare(strict_types=1);

namespace App\Services\Web\Scale;

use App\Data\Web\Scale\ScaleSpaceData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Payment;
use App\Models\Submission;

/**
 * Derives the SCALE space view-model from a dossier: whether the 75 EUR audit is paid, the consultation
 * booking state, and the action URLs. Pure derivation over the loaded relations (no side effect) so the
 * controller stays thin and never queries directly.
 */
final readonly class ScaleSpaceService
{
    public function spaceFor(Submission $submission): ScaleSpaceData
    {
        $submission->loadMissing(['payments', 'appointment']);

        $paidAudit = $submission->payments
            ->first(fn (Payment $p): bool => $p->type === PaymentType::ScaleAudit && $p->status === PaymentStatus::Succeeded);

        $appointment = $submission->appointment;

        return new ScaleSpaceData(
            reference: (string) $submission->reference,
            companyName: (string) $submission->company_name,
            cancelled: $submission->status === SubmissionStatus::Cancelled,
            auditPaid: $paidAudit !== null,
            auditAmountCents: (int) config('festilaw.scale.audit_amount_cents'),
            paidAt: $paidAudit?->paid_at,
            booked: $appointment !== null,
            appointmentStatusLabel: $appointment?->status->label(),
            scheduledAt: $appointment?->scheduled_at,
            calendarUrl: (string) config('festilaw.scale.calendar_url'),
            payUrl: route('get-started.scale.pay', ['dossier' => $submission->resume_token]),
            bookUrl: route('get-started.scale.book', ['dossier' => $submission->resume_token]),
        );
    }
}
