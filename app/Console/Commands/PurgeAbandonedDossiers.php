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
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Purge RGPD (minimisation) : supprime les dossiers a lien de reprise (STARTER + PRO + SCALE) abandonnes
 * (jamais payes) dont le lien a expire depuis plus de X jours, ainsi que leurs fichiers televerses (via
 * SubmissionObserver). Un dossier SCALE abandonne = demande d'audit soumise (statut Nouveau) jamais reglee.
 * Les dossiers ayant paye quoi que ce soit (abonnement OU audit) sont TOUJOURS conserves (relation client
 * + obligations comptables) · double garde : statut hors [Paye, Termine, Annule] ET aucun paiement reussi.
 * Seule exception pour « Annule » : le dossier annule parce que remplace par un changement de pack avant
 * paiement (replaced_by_id), purge au meme delai apres l'expiration de son lien.
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
        $failed = 0;

        // Tout type a lien de reprise/espace client porteur de donnees personnelles : STARTER + PRO
        // (parcours en ligne) et SCALE (demande d'audit). Contact reste hors perimetre.
        $purgeableTypes = array_values(array_filter(
            SubmissionType::cases(),
            fn (SubmissionType $type): bool => $type->hasOnlineJourney() || $type === SubmissionType::Scale,
        ));

        Submission::query()
            ->whereIn('type', $purgeableTypes)
            ->where(function (Builder $query): void {
                $query->whereIn('status', [
                    SubmissionStatus::New,               // SCALE abandonne : audit jamais paye
                    SubmissionStatus::InProgress,
                    SubmissionStatus::AwaitingDocuments,
                    SubmissionStatus::AwaitingPayment,
                ])
                    // Dossier annule parce que remplace (changement de pack avant paiement) : il ne fait que
                    // dupliquer l'identite du nouveau dossier, qui a repris ses pieces.
                    ->orWhere(fn (Builder $replaced): Builder => $replaced
                        ->where('status', SubmissionStatus::Cancelled)
                        ->whereNotNull('replaced_by_id'));
            })
            ->whereNotNull('resume_expires_at')
            ->where('resume_expires_at', '<', $cutoff)
            // Garde absolue : jamais un dossier ayant paye (abonnement ou audit), quel que soit son statut.
            ->whereDoesntHave('payments', fn (Builder $query): Builder => $query->where('status', PaymentStatus::Succeeded))
            ->chunkById(100, function (Collection $dossiers) use (&$deleted, &$failed): void {
                $dossiers->each(function (Submission $dossier) use (&$deleted, &$failed): void {
                    // Un fichier impossible a effacer interrompt la suppression de CE dossier (rien d'efface a
                    // moitie) : on le journalise et on continue avec les autres ; le prochain passage reessaie.
                    try {
                        $dossier->delete();
                        $deleted++;
                    } catch (Throwable $e) {
                        $failed++;
                        Log::error('Abandoned dossier purge failed; it will be retried.', ['submission' => $dossier->id, 'exception' => $e]);
                    }
                });
            });

        $this->info("Purged {$deleted} abandoned dossier(s).".($failed > 0 ? " {$failed} failed (see logs)." : ''));

        return self::SUCCESS;
    }
}
