<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Web\Starter\ApplyPackDowngradeAction;
use App\Enums\Billing\RenewalStatus;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\AdminRenewalDigest;
use App\Mail\RenewalReminder;
use App\Models\PackChange;
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
 * retards une fois la fenetre de grace depassee. Un client recoit un rappel par an, et une seule relance
 * quand il passe en retard (reponse de Laetitia du 20/11). Chaque digest admin n'est envoye qu'une fois par an
 * (anti-doublon via meta du dossier). Applique d'abord les
 * retours au Creator valides par Festilaw dont l'annee d'effet est arrivee (SC12), pour que le rappel et le
 * bouton de renouvellement annoncent deja le tarif Creator.
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
        private readonly ApplyPackDowngradeAction $applyPackDowngrade,
    ) {
        parent::__construct();
    }

    /**
     * Les envois et retours au Creator qui ont echoue pendant ce passage. Un seul suffit pour que la commande
     * reponde « echec » (audit du 23/11), comme le veut la regle des commandes ; chacun est deja au journal.
     */
    private int $failures = 0;

    public function handle(): int
    {
        $now = $this->resolveNow();
        $year = $now->year;
        $dry = (bool) $this->option('dry');

        $this->info("Renouvellements au {$now->toDateString()} (annee {$year})".($dry ? ' [DRY-RUN]' : ''));

        $this->applyDueDowngrades($year, $dry);

        /** @var list<array{company:string,pack:string,year:int,email:string,url:string}> $dueRows */
        $dueRows = [];
        /** @var list<array{company:string,pack:string,year:int,email:string,url:string}> $overdueRows */
        $overdueRows = [];
        /** @var list<Submission> $dueToMark */
        $dueToMark = [];
        /** @var list<Submission> $overdueToMark */
        $overdueToMark = [];
        $clientReminders = 0;
        $overdueReminders = 0;

        Submission::query()
            ->whereIn('type', [SubmissionType::Starter, SubmissionType::Pro])
            ->active()
            ->with('payments')
            ->chunkById(100, function (Collection $dossiers) use ($now, $year, $dry, &$dueRows, &$overdueRows, &$dueToMark, &$overdueToMark, &$clientReminders, &$overdueReminders): void {
                foreach ($dossiers as $dossier) {
                    $status = $this->renewals->status($dossier, $now);
                    if ($status === RenewalStatus::UpToDate) {
                        continue;
                    }

                    $overdue = $status === RenewalStatus::Overdue;
                    $renewalMeta = $dossier->meta['renewal'] ?? [];

                    // Rappel client : une seule fois par an, marque SEULEMENT si l'envoi a reussi (sinon
                    // le jalon annuel ne doit pas etre pose, pour que le prochain passage reessaie).
                    // Un premier rappel qui part deja en retard vaut aussi relance : le client n'en recoit pas deux.
                    if (($renewalMeta['reminded_year'] ?? null) !== $year) {
                        if ($dry) {
                            $clientReminders++;
                        } elseif ($this->sendClientReminder($dossier, $year, $overdue)) {
                            $clientReminders++;
                            $this->markRenewalMeta($dossier, 'reminded_year', $year);

                            if ($overdue) {
                                $this->markRenewalMeta($dossier, 'overdue_reminded_year', $year);
                            }
                        }
                    } elseif ($overdue && ($renewalMeta['overdue_reminded_year'] ?? null) !== $year) {
                        // Relance du client passe en retard, une seule par an, le lendemain de la fin de la fenetre
                        // de grace (Laetitia, 20/11 : « faites au mieux »). Marquee seulement si l'envoi a reussi.
                        if ($dry) {
                            $overdueReminders++;
                        } elseif ($this->sendClientReminder($dossier, $year, true)) {
                            $overdueReminders++;
                            $this->markRenewalMeta($dossier, 'overdue_reminded_year', $year);
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
            if ($dueRows !== []) {
                $this->markIfSent($this->teamNotifier->notify(new AdminRenewalDigest($dueRows, overdue: false)), $dueToMark, 'admin_notified_year', $year);
            }
            if ($overdueRows !== []) {
                $this->markIfSent($this->teamNotifier->notify(new AdminRenewalDigest($overdueRows, overdue: true)), $overdueToMark, 'overdue_alerted_year', $year);
            }
        }

        $this->info('Rappels client : '.$clientReminders.' · relances client en retard : '.$overdueReminders.' · admin a renouveler : '.count($dueRows).' · admin en retard : '.count($overdueRows).' · echecs : '.$this->failures);

        return $this->failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param  list<Submission>  $dossiers */
    private function markIfSent(bool $sent, array $dossiers, string $key, int $year): void
    {
        if (! $sent) {
            $this->failures++;

            return;
        }

        foreach ($dossiers as $dossier) {
            $this->markRenewalMeta($dossier, $key, $year);
        }
    }

    /**
     * Retours au Creator valides dont l'annee d'effet est arrivee (SC12) : le dossier passe au Creator, avec
     * un nouveau mandat Creator a signer depuis l'espace avant de renouveler.
     */
    private function applyDueDowngrades(int $year, bool $dry): void
    {
        $due = PackChange::query()
            ->where('status', PackChangeStatus::Approved)
            ->where('effective_year', '<=', $year)
            ->get();

        foreach ($due as $downgrade) {
            if ($dry) {
                $this->line("  [DRY] Retour au Creator a appliquer : dossier {$downgrade->submission_id}");

                continue;
            }

            try {
                $this->applyPackDowngrade->execute($downgrade);
            } catch (Throwable $e) {
                $this->failures++;
                Log::error('Failed to apply an approved switch back to Creator.', ['exception' => $e, 'pack_change' => $downgrade->id]);
            }
        }

        if ($due->isNotEmpty()) {
            $this->info(($dry ? '[DRY-RUN] ' : '')."Retours au Creator appliques : {$due->count()}.");
        }
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
                ->locale($dossier->locale ?: config('festilaw.default_locale'))
                ->send(new RenewalReminder($dossier, $year, $overdue));

            return true;
        } catch (Throwable $e) {
            $this->failures++;
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
