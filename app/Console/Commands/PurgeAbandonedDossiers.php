<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Purge RGPD (minimisation) : supprime les dossiers STARTER abandonnes (jamais payes) dont le lien de
 * reprise a expire depuis plus de X jours, ainsi que leurs fichiers televerses (via SubmissionObserver).
 * Les dossiers payes ou completes sont conserves (relation client + obligations comptables).
 *
 * Planifiee quotidiennement (routes/console.php). Suppression modele par modele pour declencher
 * l'observer qui efface les fichiers du disque.
 */
final class PurgeAbandonedDossiers extends Command
{
    protected $signature = 'festilaw:purge-abandoned-dossiers';

    protected $description = 'Delete abandoned (never-paid, expired) STARTER dossiers and their uploaded files.';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('festilaw.starter.abandoned_retention_days', 90));
        $deleted = 0;

        Submission::query()
            ->where('type', SubmissionType::Starter)
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
