<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Purge RGPD (minimisation) : supprime les dossiers a parcours en ligne (STARTER + PRO) abandonnes
 * (jamais payes) dont le lien de reprise a expire depuis plus de X jours, ainsi que leurs fichiers
 * televerses (via SubmissionObserver). Les dossiers payes ou completes sont conserves (relation client
 * + obligations comptables).
 *
 * Planifiee quotidiennement (routes/console.php). Suppression modele par modele pour declencher
 * l'observer qui efface les fichiers du disque.
 */
final class PurgeAbandonedDossiers extends Command
{
    protected $signature = 'festilaw:purge-abandoned-dossiers';

    protected $description = 'Delete abandoned (never-paid, expired) online-journey dossiers and their uploaded files.';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('festilaw.starter.abandoned_retention_days', 90));
        $deleted = 0;

        // Meme perimetre que le parcours client : tout type a parcours en ligne (STARTER et PRO), pas
        // seulement STARTER · sinon les fichiers prives des dossiers PRO abandonnes sont conserves sans fin.
        $onlineTypes = array_values(array_filter(
            SubmissionType::cases(),
            fn (SubmissionType $type): bool => $type->hasOnlineJourney(),
        ));

        Submission::query()
            ->whereIn('type', $onlineTypes)
            ->whereIn('status', [
                SubmissionStatus::InProgress,
                SubmissionStatus::AwaitingDocuments,
                SubmissionStatus::AwaitingPayment,
            ])
            ->whereNotNull('resume_expires_at')
            ->where('resume_expires_at', '<', $cutoff)
            ->chunkById(100, function (Collection $dossiers) use (&$deleted): void {
                $dossiers->each(function (Submission $dossier) use (&$deleted): void {
                    $dossier->delete();
                    $deleted++;
                });
            });

        $this->info("Purged {$deleted} abandoned dossier(s).");

        return self::SUCCESS;
    }
}
