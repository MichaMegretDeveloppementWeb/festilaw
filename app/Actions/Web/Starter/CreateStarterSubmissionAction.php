<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\FunnelNotification;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Opens a STARTER (Creator Pack) file: creates the submission and its (unsigned) contract shell.
 *
 * @phpstan-type StarterData array{company_name: string, company_registration_number?: string|null, website_url?: string|null, first_name: string, last_name?: string|null, email: string, phone?: string|null, contract_fields?: array<string, mixed>}
 */
final readonly class CreateStarterSubmissionAction
{
    /** @param  StarterData  $data */
    public function execute(array $data): Submission
    {
        $submission = DB::transaction(function () use ($data): Submission {
            $submission = Submission::create([
                'type' => SubmissionType::Starter,
                'status' => SubmissionStatus::InProgress,
                'locale' => app()->getLocale(),
                'company_name' => $data['company_name'],
                'company_registration_number' => $data['company_registration_number'] ?? null,
                'website_url' => $data['website_url'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            $submission->contract()->create([
                'signature_status' => SignatureStatus::Pending,
                'filled_fields' => $data['contract_fields'] ?? [],
            ]);

            return $submission;
        });

        // Notification synchrone a Festilaw, apres commit (pas de file/worker).
        Mail::to(config('festilaw.notification_email'))
            ->send(new FunnelNotification($submission, 'New Creator Pack submission'));

        return $submission;
    }
}
