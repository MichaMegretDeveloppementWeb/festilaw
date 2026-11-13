<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue d'affichage de chaque requete web.
 *
 * Deux familles de pages :
 *  - les pages publiques indexables ont UNE ADRESSE PAR LANGUE (routes/localized.php) : la langue vient
 *    de la route (metadonnee `locale`) et elle est recopiee en session, pour que le tunnel et l'espace
 *    client continuent dans la meme langue ;
 *  - toutes les autres (tunnel, espace client, /livewire/update...) n'ont qu'une adresse : la langue vient
 *    de la session, memorisee par les pages publiques ou par SwitchLocaleController.
 *
 * Applique au groupe web (apres le demarrage de session) et avant la resolution des liaisons de route.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Pas config('app.locale') : Laravel la reecrit a chaque setLocale() (langue courante, pas par defaut).
        $default = (string) config('festilaw.default_locale');
        $supported = (array) config('festilaw.supported_locales');

        $route = $request->route();
        $routeLocale = $route instanceof Route ? $route->getMetadata('locale') : null;

        if (is_string($routeLocale) && in_array($routeLocale, $supported, true)) {
            if ($request->hasSession() && $request->session()->get('locale') !== $routeLocale) {
                $request->session()->put('locale', $routeLocale);
            }

            app()->setLocale($routeLocale);

            return $next($request);
        }

        $locale = $request->hasSession()
            ? (string) $request->session()->get('locale', $default)
            : $default;

        if (! in_array($locale, $supported, true)) {
            $locale = $default;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
