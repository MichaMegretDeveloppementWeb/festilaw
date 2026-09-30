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
 * Derives the SCALE space view-model from a dossier: whether the consultation is booked, whether the
 * 75 EUR audit is paid (booking first, then payment), and the calendar / action URLs. Pure derivation over
 * the loaded relations (no side effect) so the controller stays thin and never queries directly.
 */
final readonly class ScaleSpaceService
{
    /** Google appointment booking pages that can be embedded (the short calendar.app.google link cannot). */
    private const EMBEDDABLE_HOST = 'calendar.google.com';

    private const EMBEDDABLE_PATH_PREFIX = '/calendar/appointments/schedules/';

    public function spaceFor(Submission $submission): ScaleSpaceData
    {
        $submission->loadMissing(['payments', 'appointment']);

        $paidAudit = $submission->payments
            ->first(fn (Payment $p): bool => $p->type === PaymentType::ScaleAudit && $p->status === PaymentStatus::Succeeded);

        $appointment = $submission->appointment;
        $calendarUrl = (string) config('festilaw.scale.calendar_url');

        return new ScaleSpaceData(
            reference: (string) $submission->reference,
            companyName: (string) $submission->company_name,
            cancelled: $submission->status === SubmissionStatus::Cancelled,
            auditPaid: $paidAudit !== null,
            auditAmountCents: (int) config('festilaw.scale.audit_amount_cents'),
            paidAt: $paidAudit?->paid_at,
            booked: $appointment !== null,
            appointmentStatusLabel: $appointment?->status->clientLabel(),
            scheduledAt: $appointment?->scheduled_at,
            calendarUrl: $calendarUrl,
            calendarEmbedUrl: $this->embedUrl($calendarUrl),
            payUrl: route('get-started.scale.pay', ['dossier' => $submission->resume_token]),
            bookUrl: route('get-started.scale.book', ['dossier' => $submission->resume_token]),
        );
    }

    /**
     * The embeddable version of the booking page (Google's "gv=true" embed mode), or null when the
     * configured URL is not a Google appointment booking page (short link, placeholder): the space then
     * falls back to opening the calendar in a new tab.
     */
    private function embedUrl(string $calendarUrl): ?string
    {
        $parts = parse_url($calendarUrl);

        if (($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== self::EMBEDDABLE_HOST
            || ! str_starts_with($parts['path'] ?? '', self::EMBEDDABLE_PATH_PREFIX)
            || strlen($parts['path']) <= strlen(self::EMBEDDABLE_PATH_PREFIX)) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $query['gv'] = 'true';

        return 'https://'.self::EMBEDDABLE_HOST.$parts['path'].'?'.http_build_query($query);
    }
}
