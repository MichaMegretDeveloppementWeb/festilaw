<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Billing\RenewalStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\AdminRenewalDigest;
use App\Mail\RenewalReminder;
use App\Models\Submission;
use App\Services\Billing\RenewalService;
use App\Services\Notification\TeamNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Traite les renouvellements annuels (renouvellement manuel, cf. contrat) : rappel au client quand son
 * annee de service est due, recap groupe a Festilaw des dossiers a renouveler, puis alerte groupee des
 * retards une fois la fenetre de grace depassee. Un client n'est rappele qu'une fois par an et chaque
 * digest admin n'est envoye qu'une fois par an (anti-doublon via meta du dossier).
 *
 * Planifiee quotidiennement (routes/console.php). Options :
 *  --now=AAAA-MM-JJ  simule la date (pour tester "on est en janvier")
 *  --dry             affiche ce qui serait fait, sans envoyer d'email ni modifier les dossiers
 */
final class ProcessRenewals extends Command
{
    protected $signature = 'festilaw:process-renewals {--now= : Date simulee (AAAA-MM-JJ)} {--dry : Simulation, sans envoi ni ecriture}';

    protected $description = 'Envoie les rappels de renouvellement (client + admin) et signale les retards.';

    public function __construct(
        private readonly RenewalService $renewals,
        private readonly TeamNotifier $teamNotifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = $this->resolveNow();
        $year = $now->year;
        $dry = (bool) $this->option('dry');

        $this->info("Renouvellements au {$now->toDateString()} (annee {$year})".($dry ? ' [DRY-RUN]' : ''));

        /** @var list<array{company:string,pack:string,year:int,email:string,url:string}> $dueRows */
        $dueRows = [];
        /** @var list<array{company:string,pack:string,year:int,email:string,url:string}> $overdueRows */
        $overdueRows = [];
        /** @var list<Submission> $dueToMark */
        $dueToMark = [];
        /** @var list<Submission> $overdueToMark */
        $overdueToMark = [];
        $clientReminders = 0;

        Submission::query()
            ->whereIn('type', [SubmissionType::Starter, SubmissionType::Pro])
            ->active()
            ->with('payments')
            ->chunkById(100, function (Collection $dossiers) use ($now, $year, $dry, &$dueRows, &$overdueRows, &$dueToMark, &$overdueToMark, &$clientReminders): void {
                foreach ($dossiers as $dossier) {
                    $status = $this->renewals->status($dossier, $now);
                    if ($status === RenewalStatus::UpToDate) {
                        continue;
                    }

                    $overdue = $status === RenewalStatus::Overdue;
                    $renewalMeta = $dossier->meta['renewal'] ?? [];

                    // Rappel client : une seule fois par an, marque SEULEMENT si l'envoi a reussi (sinon
                    // le jalon annuel ne doit pas etre pose, pour que le prochain passage reessaie).
                    if (($renewalMeta['reminded_year'] ?? null) !== $year) {
                        if ($dry) {
                            $clientReminders++;
                        } elseif ($this->sendClientReminder($dossier, $year, $overdue)) {
                            $clientReminders++;
                            $this->markRenewalMeta($dossier, 'reminded_year', $year);
                        }
                    }

                    // Digest admin : on collecte les lignes ET les dossiers a marquer ; le jalon ne sera
                    // pose qu'apres un envoi de digest reussi (voir apres la boucle).
                    if ($overdue) {
                        if (($renewalMeta['overdue_alerted_year'] ?? null) !== $year) {
                            $overdueRows[] = $this->row($dossier, $year);
                            $overdueToMark[] = $dossier;
                        }
                    } elseif (($renewalMeta['admin_notified_year'] ?? null) !== $year) {
                        $dueRows[] = $this->row($dossier, $year);
                        $dueToMark[] = $dossier;
                    }
                }
            });

        if (! $dry) {
            // Le jalon anti-doublon du digest n'est pose que si le digest est reellement parti.
            if ($dueRows !== [] && $this->teamNotifier->notify(new AdminRenewalDigest($dueRows, overdue: false))) {
                foreach ($dueToMark as $dossier) {
                    $this->markRenewalMeta($dossier, 'admin_notified_year', $year);
                }
            }
            if ($overdueRows !== [] && $this->teamNotifier->notify(new AdminRenewalDigest($overdueRows, overdue: true))) {
                foreach ($overdueToMark as $dossier) {
                    $this->markRenewalMeta($dossier, 'overdue_alerted_year', $year);
                }
            }
        }

        $this->info('Rappels client : '.$clientReminders.' · admin a renouveler : '.count($dueRows).' · admin en retard : '.count($overdueRows));

        return self::SUCCESS;
    }

    private function resolveNow(): CarbonImmutable
    {
        $option = $this->option('now');

        if (is_string($option) && $option !== '') {
            try {
                return CarbonImmutable::parse($option)->startOfDay();
            } catch (Throwable) {
                $this->warn("Date --now invalide ({$option}), utilisation de la date du jour.");
            }
        }

        return CarbonImmutable::now();
    }

    /** Sends the yearly client reminder. Returns whether it was actually sent (gates the meta marker). */
    private function sendClientReminder(Submission $dossier, int $year, bool $overdue): bool
    {
        if ((string) $dossier->email === '') {
            return false;
        }

        try {
            Mail::to($dossier->email)
                ->locale($dossier->locale ?: config('app.locale'))
                ->send(new RenewalReminder($dossier, $year, $overdue));

            return true;
        } catch (Throwable $e) {
            Log::error('Renewal reminder failed to send.', ['exception' => $e, 'submission' => $dossier->id]);

            return false;
        }
    }

    /** Poses un jalon d'anti-doublon annuel dans meta.renewal (appele uniquement apres un envoi reussi). */
    private function markRenewalMeta(Submission $dossier, string $key, int $year): void
    {
        $meta = $dossier->meta ?? [];
        $renewal = $meta['renewal'] ?? [];
        $renewal[$key] = $year;
        $meta['renewal'] = $renewal;
        $dossier->meta = $meta;
        $dossier->save();
    }

    /** @return array{company:string,pack:string,year:int,email:string,url:string} */
    private function row(Submission $dossier, int $year): array
    {
        return [
            'company' => (string) ($dossier->company_name ?: $dossier->email),
            'pack' => $dossier->type->label(),
            'year' => $year,
            'email' => (string) $dossier->email,
            'url' => route('admin.submissions.show', ['submission' => $dossier->id]),
        ];
    }
}
