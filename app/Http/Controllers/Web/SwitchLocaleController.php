<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bascule de langue purement visuelle : on memorise la locale choisie en session et on recharge la
 * page courante. Le site n'est PAS un multilingue reference (pas de prefixe d'URL, pas de hreflang) :
 * l'anglais reste la langue canonique du site, les autres langues ne sont qu'une traduction d'affichage.
 */
final class SwitchLocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, config('festilaw.supported_locales'), true)) {
            $request->session()->put('locale', $locale);
        }

        // Retour SAME-ORIGIN uniquement : le Referer est controle par le visiteur, on ne redirige
        // jamais vers un hote externe (pas d'open redirect). A defaut, on renvoie a l'accueil.
        $previous = url()->previous();
        $target = str_starts_with($previous, $request->schemeAndHttpHost().'/') || $previous === $request->schemeAndHttpHost()
            ? $previous
            : route('home');

        return redirect()->to($target);
    }
}
