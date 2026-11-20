<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Data\System\DailyErrorReportResult;
use App\Data\System\ErrorLogSummary;
use App\Enums\System\ErrorReportOutcome;
use App\Mail\DailyErrorReport;
use App\Repositories\SettingRepository;
use App\Services\System\ErrorLogReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Rapport d'erreurs du matin (demande de Micha du 20/11) : les erreurs du journal du serveur depuis le
 * dernier rapport, envoyees au prestataire technique (FESTILAW_TECH_ALERT_EMAIL), pour corriger avant que
 * la cliente ne s'en apercoive. Rien ne part quand le journal n'a aucune erreur.
 *
 * La periode commence au repere du dernier rapport, et ce repere n'avance qu'apres un envoi reussi : un
 * envoi en echec est repris le lendemain avec les deux jours. Elle remonte au plus a sept jours (cron arrete
 * longtemps). Aucun repli vers l'adresse de la cliente, ce rapport est technique.
 */
final readonly class SendDailyErrorReportAction
{
    public const COVERED_UNTIL_KEY = 'system.error_report.covered_until';

    private const DEFAULT_HOURS = 24;

    private const MAX_DAYS = 7;

    public function __construct(
        private ErrorLogReader $reader,
        private SettingRepository $settings,
        private ?string $recipient,
    ) {}

    public function execute(bool $dryRun = false): DailyErrorReportResult
    {
        $until = CarbonImmutable::now();
        $summary = $this->reader->read($this->since($until), $until);

        if ($dryRun) {
            return new DailyErrorReportResult(ErrorReportOutcome::DryRun, $summary);
        }

        if ($summary->isEmpty()) {
            $this->settings->put(self::COVERED_UNTIL_KEY, $until->toIso8601String());

            return new DailyErrorReportResult(ErrorReportOutcome::NothingToReport, $summary);
        }

        if (blank($this->recipient)) {
            Log::warning('Daily error report not sent: FESTILAW_TECH_ALERT_EMAIL is empty.');
            $this->keepPeriodFrom($summary);

            return new DailyErrorReportResult(ErrorReportOutcome::NoRecipient, $summary);
        }

        return $this->send($summary, (string) $this->recipient);
    }

    private function send(ErrorLogSummary $summary, string $recipient): DailyErrorReportResult
    {
        try {
            Mail::to($recipient)->send(new DailyErrorReport($summary));
        } catch (Throwable $e) {
            Log::error('Daily error report failed to send; the next report will cover this period too.', ['exception' => $e]);
            $this->keepPeriodFrom($summary);

            return new DailyErrorReportResult(ErrorReportOutcome::SendFailed, $summary);
        }

        $this->settings->put(self::COVERED_UNTIL_KEY, $summary->until->toIso8601String());

        return new DailyErrorReportResult(ErrorReportOutcome::Sent, $summary);
    }

    /**
     * Un rapport qui n'est pas parti fixe le debut de sa periode : sans repere (premier rapport), le suivant
     * ne remonterait sinon qu'a 24 heures et perdrait les erreurs de celui-ci.
     */
    private function keepPeriodFrom(ErrorLogSummary $summary): void
    {
        $this->settings->put(self::COVERED_UNTIL_KEY, $summary->since->toIso8601String());
    }

    private function since(CarbonImmutable $until): CarbonImmutable
    {
        $floor = $until->subDays(self::MAX_DAYS);
        $stored = $this->settings->get(self::COVERED_UNTIL_KEY);

        try {
            $since = $stored === null ? $until->subHours(self::DEFAULT_HOURS) : CarbonImmutable::parse($stored);
        } catch (Throwable) {
            $since = $until->subHours(self::DEFAULT_HOURS);
        }

        return $since->max($floor);
    }
}
