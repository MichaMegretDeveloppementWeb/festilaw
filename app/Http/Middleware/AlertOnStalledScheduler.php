<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\System\SchedulerHealthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function Illuminate\Support\defer;

/**
 * En PRODUCTION, une visite (client, robot, back-office, webhook) verifie apres la reponse que les taches
 * automatiques tournent encore, et previent le prestataire technique si elles sont a l'arret : le cron ne
 * peut pas donner l'alerte lui-meme puisqu'il ne tourne plus. Apres la reponse (defer) : aucun delai pour
 * le visiteur. Best-effort : ne bloque ni ne fait echouer aucune requete. Hors prod : no-op.
 */
final class AlertOnStalledScheduler
{
    public function __construct(private readonly SchedulerHealthService $health) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            // always : une page 404 ou un webhook refuse comptent aussi comme visite.
            defer(function (): void {
                try {
                    $this->health->alertIfStalled();
                } catch (Throwable) {
                    // Surveillance best-effort : jamais d'erreur visible a cause d'elle.
                }
            }, 'scheduler-health', always: true);
        }

        return $next($request);
    }
}
