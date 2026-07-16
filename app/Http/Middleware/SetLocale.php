<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Traduction visuelle : la langue d'affichage vit en SESSION, jamais dans l'URL.
 *
 * Le site a un seul jeu d'URLs et une langue canonique (l'anglais, `config('app.locale')`). Le
 * selecteur de langue memorise le choix via SwitchLocaleController ; ce middleware le lit et l'applique
 * sur chaque requete web (y compris les updates Livewire sur /livewire/update). Aucune negociation
 * d'URL, aucun hreflang : ce n'est pas un site multilingue reference, seulement une traduction d'affichage.
 *
 * Applique globalement au groupe web (apres le demarrage de session).
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $default = (string) config('app.locale');

        $locale = $request->hasSession()
            ? (string) $request->session()->get('locale', $default)
            : $default;

        if (! in_array($locale, config('festilaw.supported_locales'), true)) {
            $locale = $default;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
