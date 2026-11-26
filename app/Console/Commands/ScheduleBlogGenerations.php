<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Generation\PlanScheduledBlogGenerationsAction;
use App\Exceptions\BaseAppException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generation automatique d'articles : chaque heure pile, regarde si un creneau du calendrier regle au back-office
 * (jour et heure choisis) attend son lot, et le met en file (PlanScheduledBlogGenerationsAction). C'est le worker
 * qui l'execute ; un e-mail part quand tout le lot est termine.
 *
 * Rejouable : relancee apres le lot d'un creneau, elle ne trouve plus rien de du avant le suivant. `--dry` dit ce
 * qui serait fait sans rien mettre en file ni envoyer, ni laisser la trace de passage.
 */
final class ScheduleBlogGenerations extends Command
{
    protected $signature = 'festilaw:blog-generation-schedule
        {--dry : Dit ce qui serait fait, sans rien mettre en file ni envoyer}';

    protected $description = 'Met en file le lot d\'articles a generer quand un creneau du calendrier regle au back-office est arrive.';

    public function handle(PlanScheduledBlogGenerationsAction $plan): int
    {
        $dry = (bool) $this->option('dry');

        try {
            $result = $plan->execute($dry);
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->error($e->getUserMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            Log::error('Scheduled blog generation planning failed; it will be retried at the next hour.', ['exception' => $e]);
            $this->error('La planification des generations a echoue (voir le journal).');

            return self::FAILURE;
        }

        $prefix = $dry ? '[DRY-RUN] ' : '';

        // Hors essai a blanc, un jour sans rien a faire ne laisse aucune trace.
        if ($result->launched > 0) {
            $this->info("{$prefix}Lot planifie : {$result->launched} generation(s) mise(s) en file.");
        } elseif ($result->blockedBy !== null) {
            $this->warn("{$prefix}Lot du mais non lance : {$result->blockedBy->value}.");
        } elseif ($dry) {
            $next = $result->nextLaunchAt?->setTimezone((string) config('blog.ai.schedule_timezone'))->format('d/m/Y \a H:i');

            $this->info($result->enabled
                ? "{$prefix}Aucun lot du avant le {$next} (heure de Paris)."
                : "{$prefix}Generation automatique desactivee.");
        }

        return self::SUCCESS;
    }
}
