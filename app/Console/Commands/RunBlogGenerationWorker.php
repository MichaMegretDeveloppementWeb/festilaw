<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Services\Blog\Generation\BlogGenerationWorkerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

/**
 * Worker des generations d'articles. Relance chaque minute par le planificateur (routes/console.php, en
 * dernier). Il reste a l'ecoute : toutes les deux secondes il demande s'il y a une generation a traiter, et
 * la traite jusqu'au bout quand il y en a une (ProcessNextBlogGenerationAction).
 *
 * Relais sans trou : a vide, il s'arrete des qu'un worker plus recent s'est inscrit, ou au bout d'un delai
 * sans releve (cron arrete). Il y a donc toujours un worker a l'ecoute, et jamais deux qui traitent la meme
 * generation (la prise est atomique). Il s'arrete aussi quand le site passe en maintenance.
 *
 * Aucun processus long : passe une minute de vie, il rend la generation en cours entre deux etapes, et le
 * worker suivant la reprend. Si aucune releve n'est la (cron arrete), il la reprend lui-meme, une etape a la
 * fois.
 *
 * Simple declencheur : la file, le verrou et les etapes vivent dans les Actions et les Services.
 */
final class RunBlogGenerationWorker extends Command
{
    protected $signature = 'festilaw:blog-generation-worker
        {--once : Fait un seul passage (traite une generation s\'il y en a une) puis s\'arrete}';

    protected $description = 'Traite les generations d\'articles du blog en attente (worker relance chaque minute).';

    public function handle(ProcessNextBlogGenerationAction $processNext, BlogGenerationWorkerRegistry $registry): int
    {
        $workerId = (string) Str::uuid();
        $pollSeconds = max(1, (int) config('blog.ai.worker.poll_seconds'));
        $handoffCheckSeconds = (int) config('blog.ai.worker.handoff_check_seconds');
        $idleMaxSeconds = (int) config('blog.ai.worker.idle_max_seconds');
        $handOverAt = CarbonImmutable::now()->addSeconds((int) config('blog.ai.worker.max_lifetime_seconds'));

        $registry->register($workerId);

        $processed = 0;
        $failed = 0;
        $idleSince = CarbonImmutable::now();
        $handoffCheckedAt = CarbonImmutable::now();

        while (! app()->isDownForMaintenance()) {
            try {
                $worked = $processNext->execute($workerId, $handOverAt);
            } catch (Throwable $e) {
                // Dernier filet du worker : la generation en cause garde son verrou, qui expirera seul. Le worker
                // s'arrete la, et celui de la minute suivante reprendra : une panne qui dure (base, configuration)
                // ecrit une trace par minute au journal, et non une toutes les deux secondes (controle du 20/11).
                $failed++;
                Log::error('Blog generation worker pass failed; the generation will be retried.', ['worker' => $workerId, 'exception' => $e]);

                break;
            }

            if ($worked) {
                $processed++;
                $idleSince = CarbonImmutable::now();
            }

            if ($this->option('once')) {
                break;
            }

            $now = CarbonImmutable::now();
            $handoffIsDue = $worked || $now->getTimestamp() - $handoffCheckedAt->getTimestamp() >= $handoffCheckSeconds;

            if ($handoffIsDue) {
                if (! $registry->isCurrent($workerId)) {
                    break;
                }

                $handoffCheckedAt = $now;
            }

            if ($worked) {
                continue;
            }

            if ($now->getTimestamp() - $idleSince->getTimestamp() >= $idleMaxSeconds) {
                break;
            }

            Sleep::for($pollSeconds)->seconds();
        }

        // Un passage sans rien a faire ne laisse aucune trace (le worker tourne en permanence).
        if ($processed + $failed > 0) {
            $this->info("Blog generation worker: {$processed} working pass(es), {$failed} failed pass(es).");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
