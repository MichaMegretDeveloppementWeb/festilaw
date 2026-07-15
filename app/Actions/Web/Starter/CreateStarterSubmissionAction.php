<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Data\Starter\StarterSubmissionOutcome;
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
 * Deduplicated by email: one open dossier per email address. If an unfinished, still-resumable dossier
 * already exists for this email, no second one is created · its resume link is re-sent instead (and the
 * visitor is NOT dropped into it, since the resume token is a capability URL). The outcome tells the
 * caller which case happened.
 *
 * @phpstan-type StarterData array{company_name: string, company_registration_number?: string|null, website_url?: string|null, first_name: string, last_name?: string|null, email: string, phone?: string|null, contract_fields?: array<string, mixed>}
 */
final readonly class CreateStarterSubmissionAction
{
    /** Statuts d'un dossier "en cours" (non termine) qui bloque l'ouverture d'un doublon. */
    private const OPEN_STATUSES = [
        SubmissionStatus::InProgress,
        SubmissionStatus::AwaitingDocuments,
        SubmissionStatus::AwaitingPayment,
    ];

    /** Statuts d'une souscription active (deja payee). */
    private const ACTIVE_STATUSES = [
        SubmissionStatus::Paid,
        SubmissionStatus::Completed,
    ];

    public function __construct(
        private TeamNotifier $teamNotifier,
        private SendStarterResumeLinkAction $sendResumeLink,
    ) {}

    /** @param  StarterData  $data */
    public function execute(array $data): StarterSubmissionOutcome
    {
        // 1. Deja client actif (souscription payee) : on le renvoie vers SON dossier, jamais vers une
        // nouvelle demarche ni un ancien dossier inacheve.
        $active = $this->latestDossierIn($data['email'], self::ACTIVE_STATUSES);
        if ($active !== null) {
            $this->sendResumeLink->execute($active);

            return new StarterSubmissionOutcome($active, isNew: false, isActive: true);
        }

        // 2. Dossier en cours : on renvoie le lien pour reprendre le plus avance, sans creer de doublon
        // et sans faire entrer directement (le token vaut acces).
        $existing = $this->latestOpenDossier($data['email']);
        if ($existing !== null) {
            $this->sendResumeLink->execute($existing);

            return new StarterSubmissionOutcome($existing, isNew: false);
        }

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
        // sans casser le parcours. Puis on envoie au visiteur son lien de reprise.
        $this->teamNotifier->notify(new FunnelNotification($submission, FunnelNotificationReason::CreatorSubmission));
        $this->sendResumeLink->execute($submission);

        return new StarterSubmissionOutcome($submission, isNew: true);
    }

    /** The visitor's latest still-resumable STARTER dossier for this email in the given statuses. */
    private function latestDossierIn(string $email, array $statuses): ?Submission
    {
        return Submission::query()
            ->where('type', SubmissionType::Starter)
            ->where('email', $email)
            ->whereIn('status', $statuses)
            ->resumable()
            ->latest()
            ->first();
    }

    /** The visitor's unfinished dossier for this email, most advanced first (closest to done). */
    private function latestOpenDossier(string $email): ?Submission
    {
        return Submission::query()
            ->where('type', SubmissionType::Starter)
            ->where('email', $email)
            ->whereIn('status', self::OPEN_STATUSES)
            ->resumable()
            // Le plus avance d'abord (portable SQLite + MySQL, contrairement a FIELD()).
            ->orderByRaw("CASE status WHEN 'awaiting_payment' THEN 0 WHEN 'awaiting_documents' THEN 1 ELSE 2 END")
            ->latest()
            ->first();
    }
}
