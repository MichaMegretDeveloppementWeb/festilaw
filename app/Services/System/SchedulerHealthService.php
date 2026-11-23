<?php

declare(strict_types=1);

namespace App\Services\System;

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
        $value = $this->settings->get(self::HEARTBEAT_KEY);

        try {
            return $value === null ? null : CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
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

        $recipient = config('festilaw.tech_alert_email') ?: config('festilaw.notification_email');

        try {
            Mail::to($recipient)->send(new SchedulerStalledAlert($lastRunAt));
        } catch (Throwable $e) {
            Log::error('Scheduler stalled alert failed to send.', ['exception' => $e]);
        }
    }
}
