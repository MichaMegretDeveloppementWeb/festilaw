<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue d'une adresse prefixee (/fr/..., /es/...) fixee des le debut de la requete, avant le routage.
 *
 * Les pages publiques trouvees sont deja traitees par SetLocale (groupe web), qui lit la langue de la
 * route. Ce middleware global ne sert qu'aux adresses INCONNUES sous un prefixe de langue : leur 404 est
 * rendue avant le groupe web (pas de session, pas de SetLocale), et s'afficherait sinon en anglais.
 */
final class SetLocaleFromPathPrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        $prefix = $request->segment(1);

        if (is_string($prefix)
            && $prefix !== config('festilaw.default_locale')
            && in_array($prefix, config('festilaw.supported_locales'), true)) {
            app()->setLocale($prefix);
        }

        return $next($request);
    }
}
