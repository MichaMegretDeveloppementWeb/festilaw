<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Actions\System\SendDailyErrorReportAction;
use App\Mail\ErrorReportMissingAlert;
use App\Mail\SchedulerStalledAlert;
use App\Repositories\SettingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sante des taches automatiques. Un seul cron (`schedule:run` chaque minute) declenche les rappels de
 * renouvellement, la reconciliation des paiements et des signatures, la recuperation des PDF signes et les
 * purges RGPD : s'il s'arrete, tout s'arrete sans bruit. La commande festilaw:scheduler-heartbeat note
 * chaque passage (table settings, pas le cache que vide deploy.sh) ; au-dela du seuil sans passage, les
 * taches sont a l'arret.
 *
 * Aucun passage enregistre (installation neuve) n'est pas un arret : deploy.sh enregistre un premier
 * passage, si bien qu'un cron jamais configure se voit apres le seuil.
 *
 * Le rapport d'erreurs du matin est surveille de la meme facon (constat du 26/11 : il n'avait jamais tourne en
 * production, le cron passant une minute sur deux) : sans rapport mene a bien depuis 26 heures, le prestataire est
 * prevenu. Un rapport qui n'avait rien a envoyer compte comme mene a bien.
 */
final readonly class SchedulerHealthService
{
    public const HEARTBEAT_KEY = 'scheduler.last_run_at';

    /**
     * Une seule alerte par arret (decision de Micha du 23/11) : la marque porte la date du dernier passage, si bien
     * qu'un nouvel arret, apres une reprise du cron, donne une nouvelle alerte. Elle vit dans le cache en base, que
     * deploy.sh vide, mais deploy.sh enregistre aussi un passage, ce qui termine l'arret.
     */
    private const ALERT_MARK_DAYS = 30;

    public function __construct(private SettingRepository $settings) {}

    public function recordHeartbeat(): void
    {
        $this->settings->put(self::HEARTBEAT_KEY, now()->toIso8601String());
    }

    public function lastRunAt(): ?CarbonImmutable
    {
        return $this->moment($this->settings->get(self::HEARTBEAT_KEY));
    }

    /**
     * Le moment du dernier rapport d'erreurs mene a bien, quand il date de plus de `error_report_stale_hours`.
     * Nul tant que le rapport tourne, ou s'il n'a encore aucune trace : la migration qui a pose cette surveillance
     * en a ecrit une premiere au deploiement.
     */
    public function errorReportMissingSince(): ?CarbonImmutable
    {
        return $this->reportMissingSince($this->moment($this->settings->get(SendDailyErrorReportAction::LAST_DONE_KEY)));
    }

    public function isStalled(): bool
    {
        return $this->stalledSince($this->lastRunAt());
    }

    private function stalledSince(?CarbonImmutable $lastRunAt): bool
    {
        return $lastRunAt !== null
            && $lastRunAt->lt(now()->subMinutes((int) config('festilaw.scheduler.stale_after_minutes', 15)));
    }

    /**
     * Previent le prestataire technique (a defaut l'equipe) que les taches sont a l'arret. Declenchee par
     * les visites du site (AlertOnStalledScheduler), puisque le cron lui-meme ne tourne plus. Best-effort :
     * un echec d'envoi est journalise, jamais leve.
     */
    public function alertIfStalled(): void
    {
        $lastRunAt = $this->lastRunAt();

        if (! $this->stalledSince($lastRunAt)) {
            return;
        }

        // Cache::add est atomique : deux visites simultanees n'envoient pas deux alertes.
        if (! Cache::add('scheduler-stalled-alerted:'.$lastRunAt->getTimestamp(), true, now()->addDays(self::ALERT_MARK_DAYS))) {
            return;
        }

        try {
            Mail::to($this->recipient())->send(new SchedulerStalledAlert($lastRunAt));
        } catch (Throwable $e) {
            Log::error('Scheduler stalled alert failed to send.', ['exception' => $e]);
        }
    }

    /**
     * Ce que verifie chaque visite du site en production (AlertOnStalledScheduler) : les taches a l'arret d'abord,
     * puis, si elles tournent, le rapport d'erreurs du matin. Deux reglages lus en une requete. Une seule alerte par
     * arret, comme pour le cron. Best-effort : un echec d'envoi est journalise, jamais leve.
     */
    public function alertIfStopped(): void
    {
        $values = $this->settings->many([self::HEARTBEAT_KEY, SendDailyErrorReportAction::LAST_DONE_KEY]);

        if ($this->stalledSince($this->moment($values[self::HEARTBEAT_KEY]))) {
            $this->alertIfStalled();

            return;
        }

        $reportDoneAt = $this->reportMissingSince($this->moment($values[SendDailyErrorReportAction::LAST_DONE_KEY]));

        if ($reportDoneAt === null || ! Cache::add('error-report-missing-alerted:'.$reportDoneAt->getTimestamp(), true, now()->addDays(self::ALERT_MARK_DAYS))) {
            return;
        }

        try {
            Mail::to($this->recipient())->send(new ErrorReportMissingAlert($reportDoneAt));
        } catch (Throwable $e) {
            Log::error('Missing error report alert failed to send.', ['exception' => $e]);
        }
    }

    private function reportMissingSince(?CarbonImmutable $doneAt): ?CarbonImmutable
    {
        return $doneAt !== null && $doneAt->lt(now()->subHours((int) config('festilaw.scheduler.error_report_stale_hours', 26)))
            ? $doneAt
            : null;
    }

    private function recipient(): string
    {
        return (string) (config('festilaw.tech_alert_email') ?: config('festilaw.notification_email'));
    }

    private function moment(?string $value): ?CarbonImmutable
    {
        try {
            return $value === null ? null : CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
