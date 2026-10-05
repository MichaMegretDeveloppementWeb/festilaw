<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\SchedulerHealthService;
use Illuminate\Console\Command;

/**
 * Battement des taches automatiques : planifiee chaque minute, elle note l'heure du passage. Si le cron
 * s'arrete, ce battement aussi : le back-office l'affiche et le prestataire technique est prevenu par
 * e-mail (SchedulerHealthService). Lancee aussi par deploy.sh, pour qu'un cron jamais configure se voie.
 */
final class RecordSchedulerHeartbeat extends Command
{
    protected $signature = 'festilaw:scheduler-heartbeat';

    protected $description = 'Note le passage des taches automatiques (surveillance du cron).';

    public function handle(SchedulerHealthService $health): int
    {
        $health->recordHeartbeat();
        $this->info('Battement des taches automatiques enregistre.');

        return self::SUCCESS;
    }
}
