<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Billing\RenewalStatus;
use App\Enums\Submission\SubmissionStatus;
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
        $clientReminders = 0;

        Submission::query()
            ->whereIn('type', [SubmissionType::Starter, SubmissionType::Pro])
            ->whereIn('status', [SubmissionStatus::Paid, SubmissionStatus::Completed])
            ->with('payments')
            ->chunkById(100, function (Collection $dossiers) use ($now, $year, $dry, &$dueRows, &$overdueRows, &$clientReminders): void {
                foreach ($dossiers as $dossier) {
                    $status = $this->renewals->status($dossier, $now);
                    if ($status === RenewalStatus::UpToDate) {
                        continue;
                    }

                    $overdue = $status === RenewalStatus::Overdue;
                    $renewalMeta = $dossier->meta['renewal'] ?? [];
                    $changed = false;

                    // Rappel client : une seule fois par an (dans l'etat ou le dossier est vu en premier).
                    if (($renewalMeta['reminded_year'] ?? null) !== $year) {
                        $clientReminders++;
                        if (! $dry) {
                            $this->sendClientReminder($dossier, $year, $overdue);
                            $renewalMeta['reminded_year'] = $year;
                            $changed = true;
                        }
                    }

                    // Digest admin : une fois par an et par etat (a renouveler / en retard).
                    if ($overdue) {
                        if (($renewalMeta['overdue_alerted_year'] ?? null) !== $year) {
                            $overdueRows[] = $this->row($dossier, $year);
                            if (! $dry) {
                                $renewalMeta['overdue_alerted_year'] = $year;
                                $changed = true;
                            }
                        }
                    } elseif (($renewalMeta['admin_notified_year'] ?? null) !== $year) {
                        $dueRows[] = $this->row($dossier, $year);
                        if (! $dry) {
                            $renewalMeta['admin_notified_year'] = $year;
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        $meta = $dossier->meta ?? [];
                        $meta['renewal'] = $renewalMeta;
                        $dossier->meta = $meta;
                        $dossier->save();
                    }
                }
            });

        if (! $dry) {
            if ($dueRows !== []) {
                $this->teamNotifier->notify(new AdminRenewalDigest($dueRows, overdue: false));
            }
            if ($overdueRows !== []) {
                $this->teamNotifier->notify(new AdminRenewalDigest($overdueRows, overdue: true));
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

    private function sendClientReminder(Submission $dossier, int $year, bool $overdue): void
    {
        if ((string) $dossier->email === '') {
            return;
        }

        try {
            Mail::to($dossier->email)
                ->locale($dossier->locale ?: config('app.locale'))
                ->send(new RenewalReminder($dossier, $year, $overdue));
        } catch (Throwable $e) {
            Log::error('Renewal reminder failed to send.', ['exception' => $e, 'submission' => $dossier->id]);
        }
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
