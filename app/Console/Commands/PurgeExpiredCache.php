<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\System\PurgeExpiredCacheAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Efface les lignes expirees du cache en base, chaque nuit (routes/console.php). Ne fait rien quand le cache
 * n'est pas en base (tests, poste local en fichiers). Rejouable.
 *  --dry  compte ce qui serait efface, sans rien effacer
 */
final class PurgeExpiredCache extends Command
{
    protected $signature = 'festilaw:purge-expired-cache {--dry : Simulation, sans suppression}';

    protected $description = 'Efface les lignes expirees du cache en base de donnees.';

    public function handle(PurgeExpiredCacheAction $purge): int
    {
        if (config('cache.default') !== 'database') {
            $this->info('Cache: not stored in the database, nothing to purge.');

            return self::SUCCESS;
        }

        try {
            $dry = (bool) $this->option('dry');
            $counts = $purge->execute($dry);
        } catch (Throwable $e) {
            Log::error('Expired cache purge failed; it will be retried tomorrow.', ['exception' => $e]);
            $this->error('Cache purge failed, see the log.');

            return self::FAILURE;
        }

        $this->info(($dry ? 'Cache (dry run): ' : 'Cache: ')."{$counts['entries']} expired entr(y/ies) and {$counts['locks']} expired lock(s) ".($dry ? 'would be deleted.' : 'deleted.'));

        return self::SUCCESS;
    }
}
