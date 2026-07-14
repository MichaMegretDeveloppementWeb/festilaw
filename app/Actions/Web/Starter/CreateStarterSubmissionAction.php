<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\SignatureStatus;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\FunnelNotification;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens a STARTER (Creator Pack) file: creates the submission and its (unsigned) contract shell.
 *
 * @phpstan-type StarterData array{company_name: string, company_registration_number?: string|null, website_url?: string|null, first_name: string, last_name?: string|null, email: string, phone?: string|null, contract_fields?: array<string, mixed>}
 */
final readonly class CreateStarterSubmissionAction
{
    public function __construct(private TeamNotifier $teamNotifier) {}

    /** @param  StarterData  $data */
    public function execute(array $data): Submission
    {
        // Deux ecritures (submission + contract) => transaction justifiee.
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
                // Lien de reprise (capability URL) : le dossier se poursuit sans compte a creer.
                'resume_token' => Str::random(48),
                'resume_expires_at' => now()->addDays((int) config('festilaw.starter.resume_ttl_days', 30)),
            ]);

            $submission->contract()->create([
                'signature_status' => SignatureStatus::Pending,
                'filled_fields' => $data['contract_fields'] ?? [],
            ]);

            return $submission;
        });

        // Notification synchrone a Festilaw, apres commit (pas de file/worker) ; un echec est logue
        // sans casser le parcours.
        $this->teamNotifier->notify(new FunnelNotification($submission, FunnelNotificationReason::CreatorSubmission));

        return $submission;
    }
}
