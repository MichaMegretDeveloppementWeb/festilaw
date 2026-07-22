<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\System\ProductionSafetyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde-fou fail-closed : en PRODUCTION, si la configuration est apte a simuler ou falsifier un
 * paiement/une signature (faux prestataires, mode test, mail simule, debug...), on refuse de servir la
 * requete (503) plutot que d'encaisser en simulation. Global : couvre aussi le webhook fake. Hors
 * production, on ne fait rien (dev/test tournent en fake par design).
 */
final class EnsureProductionIsConfigured
{
    public function __construct(private readonly ProductionSafetyService $safety) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            $violations = $this->safety->violations();

            if ($violations !== []) {
                Log::critical('Production misconfigured, refusing to serve.', ['violations' => $violations]);

                abort(503, 'Service temporairement indisponible.');
            }
        }

        return $next($request);
    }
}
