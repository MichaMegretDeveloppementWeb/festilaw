<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    /**
     * Lit la locale depuis le prefixe d'URL (`/{locale}/...`), la valide,
     * l'applique, et la fixe comme parametre par defaut des URLs generees.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (! in_array($locale, config('festilaw.supported_locales'), true)) {
            abort(404);
        }

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
