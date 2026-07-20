<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tetes de securite HTTP de base, appliques a chaque reponse web.
 *
 * Pas de Content-Security-Policy : Livewire/Alpine inline des ressources, une CSP stricte casserait
 * le site. Referrer-Policy compte ici car le token de reprise du dossier voyage dans l'URL.
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

        // Pas de Disallow robots.txt pour /admin : cela empecherait les moteurs de VOIR ce noindex.
        if ($request->is('admin', 'admin/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
