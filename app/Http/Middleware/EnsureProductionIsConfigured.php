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
 * emails / la generation d'articles (faux prestataires, mode test, mail simule, debug, cle manquante...), on
 * le journalise en ERREUR SANS bloquer la requete : une mise en ligne peut etre volontairement partielle (ex.
 * SIGNWELL_TEST_MODE le temps d'une recette). En erreur, le manquement part au rapport du matin, une fois par
 * jour et par jeu de manquements (audit du 23/11 : en warning, personne ne le voyait). deploy.sh lance aussi
 * `festilaw:check-production` en fin de deploiement. Hors prod : no-op.
 */
final class EnsureProductionIsConfigured
{
    /** Une entree par jour suffit : le rapport d'erreurs du matin la porte tant que le manquement dure. */
    private const WARN_THROTTLE_MINUTES = 1_440;

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
                Log::error('Production configuration is incomplete (non-blocking).', ['violations' => $violations]);
            }
        } catch (Throwable $e) {
            // Avertissement best-effort : on avale tout pour garantir que la requete passe malgre tout.
        }
    }
}
