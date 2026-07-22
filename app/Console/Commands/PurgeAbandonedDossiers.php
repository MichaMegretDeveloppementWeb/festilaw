<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Purge RGPD (minimisation) : supprime les dossiers a lien de reprise (STARTER + PRO + SCALE) abandonnes
 * (jamais payes) dont le lien a expire depuis plus de X jours, ainsi que leurs fichiers televerses (via
 * SubmissionObserver). Un dossier SCALE abandonne = demande d'audit soumise (statut Nouveau) jamais reglee.
 * Les dossiers ayant paye quoi que ce soit (abonnement OU audit) sont TOUJOURS conserves (relation client
 * + obligations comptables) · double garde : statut hors [Paye, Termine, Annule] ET aucun paiement reussi.
 *
 * Planifiee quotidiennement (routes/console.php). Suppression modele par modele pour declencher
 * l'observer qui efface les fichiers du disque.
 */
final class PurgeAbandonedDossiers extends Command
{
    protected $signature = 'festilaw:purge-abandoned-dossiers';

    protected $description = 'Delete abandoned (never-paid, expired) dossiers with a resume link and their uploaded files.';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('festilaw.starter.abandoned_retention_days', 90));
        $deleted = 0;

        // Tout type a lien de reprise/espace client porteur de donnees personnelles : STARTER + PRO
        // (parcours en ligne) et SCALE (demande d'audit). Contact reste hors perimetre.
        $purgeableTypes = array_values(array_filter(
            SubmissionType::cases(),
            fn (SubmissionType $type): bool => $type->hasOnlineJourney() || $type === SubmissionType::Scale,
        ));

        Submission::query()
            ->whereIn('type', $purgeableTypes)
            ->whereIn('status', [
                SubmissionStatus::New,               // SCALE abandonne : audit jamais paye
                SubmissionStatus::InProgress,
                SubmissionStatus::AwaitingDocuments,
                SubmissionStatus::AwaitingPayment,
            ])
            ->whereNotNull('resume_expires_at')
            ->where('resume_expires_at', '<', $cutoff)
            // Garde absolue : jamais un dossier ayant paye (abonnement ou audit), quel que soit son statut.
            ->whereDoesntHave('payments', fn (Builder $query): Builder => $query->where('status', PaymentStatus::Succeeded))
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
