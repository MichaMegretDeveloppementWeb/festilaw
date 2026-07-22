<?php

declare(strict_types=1);

namespace App\Actions\Web\Scale;

use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\FunnelNotification;
use App\Mail\ScaleSpaceLink;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * SCALE parcours: opens the file, then hands the visitor a capability link to their "Scale space" where
 * they pay the audit fee and book the consultation. The resume_token is the space's access key (magic
 * link), emailed so they can return on any device without an account. Notifies the team of the request.
 *
 * @phpstan-type ScaleData array{company_name: string, email: string, first_name?: string|null, website_url?: string|null, phone?: string|null, eu_sales_countries?: array<int, string>|null, product_types?: string|null}
 */
final readonly class CreateScaleSubmissionAction
{
    public function __construct(private TeamNotifier $teamNotifier) {}

    /** @param  ScaleData  $data */
    public function execute(array $data): Submission
    {
        // Ecriture unique : pas de transaction.
        $submission = Submission::create([
            'type' => SubmissionType::Scale,
            'status' => SubmissionStatus::New,
            'locale' => app()->getLocale(),
            'company_name' => $data['company_name'],
            'email' => $data['email'],
            'first_name' => $data['first_name'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'phone' => $data['phone'] ?? null,
            'eu_sales_countries' => $data['eu_sales_countries'] ?? null,
            'product_types' => $data['product_types'] ?? null,
            'resume_token' => Str::random(48),
            'resume_expires_at' => now()->addDays((int) config('festilaw.scale.resume_ttl_days', 30)),
        ]);

        $this->teamNotifier->notify(new FunnelNotification($submission, FunnelNotificationReason::ScaleAuditRequest));
        $this->emailSpaceLink($submission);

        return $submission;
    }

    /** Emails the capability link to the Scale space. Peripheral side effect: a failure is logged, never breaks the flow. */
    private function emailSpaceLink(Submission $submission): void
    {
        if ((string) $submission->email === '') {
            return;
        }

        try {
            Mail::to($submission->email)
                ->locale($submission->locale ?: config('app.locale'))
                ->send(new ScaleSpaceLink($submission));
        } catch (Throwable $e) {
            Log::error('Failed to send the SCALE space link.', [
                'exception' => $e,
                'submission' => $submission->id,
            ]);
        }
    }
}
