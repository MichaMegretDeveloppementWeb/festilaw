<?php

declare(strict_types=1);

namespace App\Actions\Web\Scale;

use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\FunnelNotification;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;

/**
 * SCALE parcours: opens the file before paying the audit fee.
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
        ]);

        $this->teamNotifier->notify(new FunnelNotification($submission, FunnelNotificationReason::ScaleAuditRequest));

        return $submission;
    }
}
