<?php

declare(strict_types=1);

namespace App\Actions\Web\Pro;

use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\FunnelNotification;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;

/**
 * PRO parcours: records the enquiry, then the UI redirects the visitor to WhatsApp Business.
 * Status stays New until Festilaw handles it (manual transition to Completed in the back-office).
 *
 * @phpstan-type ProData array{company_name: string, email: string, first_name?: string|null, website_url?: string|null, phone?: string|null, eu_sales_countries?: array<int, string>|null, product_types?: string|null}
 */
final readonly class CreateProSubmissionAction
{
    public function __construct(private TeamNotifier $teamNotifier) {}

    /** @param  ProData  $data */
    public function execute(array $data): Submission
    {
        // Ecriture unique : pas de transaction (cf. architecture-couches, pragmatisme).
        $submission = Submission::create([
            'type' => SubmissionType::Pro,
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

        $this->teamNotifier->notify(new FunnelNotification($submission, FunnelNotificationReason::ProEnquiry));

        return $submission;
    }
}
