<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\System\ProductionSafetyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * En PRODUCTION, si la configuration est apte a simuler ou degrader un paiement / une signature / les
 * emails (faux prestataires, mode test, mail simule, debug...), on trace un AVERTISSEMENT dans les logs
 * SANS bloquer la requete : une mise en ligne peut etre volontairement partielle (ex. SIGNWELL_TEST_MODE
 * le temps d'une recette, SMTP pas encore branche). L'avertissement est throttle (au plus une fois par
 * heure et par jeu de manquements) pour ne pas saturer les logs. La verification bloquante reste
 * disponible a la demande via `festilaw:check-production` (checklist go-live / CI). Hors prod : no-op.
 */
final class EnsureProductionIsConfigured
{
    /** Fenetre anti-spam du log d'avertissement (une entree par heure suffit a signaler l'etat). */
    private const WARN_THROTTLE_MINUTES = 60;

    public function __construct(private readonly ProductionSafetyService $safety) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            $this->warnIfMisconfigured();
        }

        return $next($request);
    }

    /**
     * Best-effort : ne doit JAMAIS lever ni bloquer la requete (un souci de cache/log ne peut pas rendre
     * le site indisponible). L'empreinte du jeu de manquements sert de cle : un changement de config
     * (ex. SMTP branche) re-loggue sans attendre la fin de la fenetre.
     */
    private function warnIfMisconfigured(): void
    {
        try {
            $violations = $this->safety->violations();

            if ($violations === []) {
                return;
            }

            $fingerprint = substr(hash('sha256', implode('|', $violations)), 0, 16);

            // Cache::add est atomique : ne loggue qu'a la premiere requete de la fenetre pour ce jeu.
            if (Cache::add('production-safety-warned:'.$fingerprint, true, now()->addMinutes(self::WARN_THROTTLE_MINUTES))) {
                Log::warning('Production configuration warnings (non-blocking).', ['violations' => $violations]);
            }
        } catch (Throwable $e) {
            // Avertissement best-effort : on avale tout pour garantir que la requete passe malgre tout.
        }
    }
}
