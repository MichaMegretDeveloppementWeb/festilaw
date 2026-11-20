<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\System\SendDailyErrorReportAction;
use App\Data\System\ErrorLogGroup;
use App\Enums\System\ErrorReportOutcome;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rapport d'erreurs du matin : envoie au prestataire technique les erreurs du journal du serveur depuis le
 * dernier rapport (SendDailyErrorReportAction), rien quand il n'y en a pas. Rejouable : le repere de la
 * periode couverte n'avance qu'apres un envoi reussi. `--dry` affiche le rapport sans l'envoyer.
 */
final class SendDailyErrorReport extends Command
{
    protected $signature = 'festilaw:error-report
        {--dry : Affiche le rapport sans l\'envoyer ni avancer la periode couverte}';

    protected $description = 'Envoie au prestataire technique les erreurs du journal du serveur depuis le dernier rapport.';

    public function handle(SendDailyErrorReportAction $report): int
    {
        try {
            $result = $report->execute((bool) $this->option('dry'));
        } catch (Throwable $e) {
            Log::error('Daily error report could not be built.', ['exception' => $e]);
            $this->error('Le rapport d\'erreurs n\'a pas pu etre prepare (voir le journal).');

            return self::FAILURE;
        }

        $summary = $result->summary;

        if ($result->outcome === ErrorReportOutcome::DryRun) {
            $this->info("[DRY-RUN] {$summary->occurrences()} erreur(s) du {$summary->since->toDateTimeString()} au {$summary->until->toDateTimeString()} (UTC).");

            foreach ($summary->groups as $group) {
                $this->line($this->describe($group));
            }

            foreach ($summary->unreadableFiles as $file) {
                $this->warn("Fichier illisible : {$file}");
            }

            return self::SUCCESS;
        }

        return match ($result->outcome) {
            ErrorReportOutcome::Sent => $this->done("Rapport envoye : {$summary->occurrences()} erreur(s)."),
            ErrorReportOutcome::NoRecipient => $this->failed('Rapport non envoye : FESTILAW_TECH_ALERT_EMAIL est vide.'),
            ErrorReportOutcome::SendFailed => $this->failed('Rapport non envoye : l\'envoi a echoue (voir le journal). Le prochain reprendra cette periode.'),
            default => self::SUCCESS,
        };
    }

    private function describe(ErrorLogGroup $group): string
    {
        $exception = $group->exceptionClass === null ? '' : " [{$group->exceptionClass}]";

        return "  {$group->level} x{$group->count} {$group->message}{$exception} ({$group->lastFile})";
    }

    private function done(string $message): int
    {
        $this->info($message);

        return self::SUCCESS;
    }

    private function failed(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
