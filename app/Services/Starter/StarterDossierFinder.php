<?php

declare(strict_types=1);

namespace App\Services\Starter;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche de dossier STARTER par email (dedup + reprise). Centralise ici pour que la regle
 * "quel dossier surfacer pour un email" ne vive qu'a un seul endroit : la dedup de
 * CreateStarterSubmissionAction et le lien de reprise de AccessFileForm l'appellent, plutot que de
 * dupliquer la meme requete + ordonnancement.
 */
final class StarterDossierFinder
{
    /** Souscription active (deja payee). */
    private const ACTIVE_STATUSES = [
        SubmissionStatus::Paid,
        SubmissionStatus::Completed,
    ];

    /** Dossier "en cours" (non termine), du plus avance au moins avance. */
    private const OPEN_STATUSES = [
        SubmissionStatus::AwaitingPayment,
        SubmissionStatus::AwaitingDocuments,
        SubmissionStatus::InProgress,
    ];

    /** Le dossier actif (paye) le plus recent, encore resumable. */
    public function latestActiveForEmail(string $email): ?Submission
    {
        return $this->resumableFor($email)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest()
            ->first();
    }

    /** Le dossier ouvert (non paye) le plus AVANCE (au plus proche de la fin), encore resumable. */
    public function latestOpenForEmail(string $email): ?Submission
    {
        return $this->resumableFor($email)
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderByRaw("CASE status WHEN 'awaiting_payment' THEN 0 WHEN 'awaiting_documents' THEN 1 ELSE 2 END")
            ->latest()
            ->first();
    }

    /** Le dossier le plus pertinent (actif d'abord, sinon le plus avance) pour un lien de reprise. */
    public function mostRelevantResumableForEmail(string $email): ?Submission
    {
        return $this->resumableFor($email)
            ->whereIn('status', [...self::ACTIVE_STATUSES, ...self::OPEN_STATUSES])
            ->orderByRaw("CASE status WHEN 'paid' THEN 0 WHEN 'completed' THEN 1 WHEN 'awaiting_payment' THEN 2 WHEN 'awaiting_documents' THEN 3 ELSE 4 END")
            ->latest()
            ->first();
    }

    /** @return Builder<Submission> */
    private function resumableFor(string $email): Builder
    {
        return Submission::query()
            ->where('type', SubmissionType::Starter)
            ->where('email', $email)
            ->resumable();
    }
}
