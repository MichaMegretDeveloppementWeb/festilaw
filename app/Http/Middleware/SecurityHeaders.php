<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tetes de securite HTTP de base, appliques a chaque reponse web.
 *
 * Volontairement sans Content-Security-Policy : Livewire/Alpine et Bunny Fonts injectent/inline des
 * ressources, une CSP stricte casserait le site. On pose uniquement les en-tetes surs et sans effet
 * de bord. Referrer-Policy est important ici car le token de reprise du dossier voyage dans l'URL.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
