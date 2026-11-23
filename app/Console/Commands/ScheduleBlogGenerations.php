<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Generation\PlanScheduledBlogGenerationsAction;
use App\Exceptions\BaseAppException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generation automatique d'articles : chaque heure de 6 h a 20 h (UTC), regarde si un lot est du d'apres la periode reglee au
 * back-office, et le met en file (PlanScheduledBlogGenerationsAction). C'est le worker qui l'execute ; un
 * e-mail part quand tout le lot est termine.
 *
 * Rejouable : relancee dans la meme periode, elle ne trouve plus rien de du. `--dry` dit ce qui serait fait sans
 * rien mettre en file ni envoyer.
 */
final class ScheduleBlogGenerations extends Command
{
    protected $signature = 'festilaw:blog-generation-schedule
        {--dry : Dit ce qui serait fait, sans rien mettre en file ni envoyer}';

    protected $description = 'Met en file le lot d\'articles a generer quand la periode reglee au back-office est echue.';

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
            Log::error('Scheduled blog generation planning failed; it will be retried tomorrow.', ['exception' => $e]);
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
            $this->info($result->enabled
                ? "{$prefix}Aucun lot du avant le {$result->dueAt?->format('d/m/Y')}."
                : "{$prefix}Generation automatique desactivee.");
        }

        return self::SUCCESS;
    }
}
