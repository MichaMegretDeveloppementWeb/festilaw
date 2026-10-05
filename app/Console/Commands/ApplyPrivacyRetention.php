<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Applique deux engagements de la politique de confidentialite (decisions de Festilaw du 04-05/10/2026) :
 *
 * 1. Fin de la relation = dossier annule : son acces client (lien magique) est ferme. L'annulation au
 *    back-office le fait deja a l'instant (ChangeSubmissionStatusAction) ; ce passage rattrape les dossiers
 *    annules avant cette regle et sert de filet.
 * 2. Demandes de contact sans suite : supprimees retention_months mois apres le dernier echange
 *    (updated_at, tenu a jour par les notes internes et les e-mails envoyes depuis le back-office). Les
 *    notes partent avec la demande (cascade).
 *
 * Planifiee quotidiennement (routes/console.php), idempotente. Option --dry : compte sans rien modifier.
 */
final class ApplyPrivacyRetention extends Command
{
    protected $signature = 'festilaw:apply-privacy-retention {--dry : Simulation, sans ecriture}';

    protected $description = 'Ferme l\'acces des dossiers annules et supprime les demandes de contact sans echange depuis 12 mois.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $closed = $this->closeCancelledAccess($dry);
        $deleted = $this->deleteStaleContactRequests($dry);

        $prefix = $dry ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}Acces fermes (dossiers annules) : {$closed}.");
        $this->info("{$prefix}Demandes de contact supprimees : {$deleted}.");

        Log::info('privacy.retention_applied', [
            'dry' => $dry,
            'access_closed' => $closed,
            'contact_requests_deleted' => $deleted,
        ]);

        return self::SUCCESS;
    }

    private function closeCancelledAccess(bool $dry): int
    {
        $closed = 0;

        Submission::query()
            ->where('status', SubmissionStatus::Cancelled)
            ->whereNotNull('resume_token')
            ->resumable()
            ->chunkById(100, function (Collection $dossiers) use ($dry, &$closed): void {
                $dossiers->each(function (Submission $dossier) use ($dry, &$closed): void {
                    if (! $dry) {
                        $dossier->closeAccess();
                    }
                    $closed++;
                });
            });

        return $closed;
    }

    private function deleteStaleContactRequests(bool $dry): int
    {
        $cutoff = now()->subMonths((int) config('festilaw.contact.retention_months', 12));
        $deleted = 0;

        Submission::query()
            ->where('type', SubmissionType::Contact)
            ->where('updated_at', '<', $cutoff)
            ->chunkById(100, function (Collection $requests) use ($dry, &$deleted): void {
                $requests->each(function (Submission $request) use ($dry, &$deleted): void {
                    if (! $dry) {
                        $request->delete();
                    }
                    $deleted++;
                });
            });

        return $deleted;
    }
}
