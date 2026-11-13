<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bascule de langue des pages SANS adresse par langue (tunnel, espace client) : on memorise la langue
 * choisie en session et on renvoie a la page precedente.
 *
 * Les pages publiques ont une adresse par langue et un lien direct dans le selecteur ; si l'on arrive
 * quand meme ici depuis l'une d'elles (ancien lien, favori), on renvoie a son equivalent dans la langue
 * demandee plutot qu'a la meme adresse, qui reimposerait sa propre langue.
 */
final class SwitchLocaleController extends Controller
{
    public function __invoke(Request $request, string $locale, LocalizedUrlGenerator $localizedUrls): RedirectResponse
    {
        $supported = in_array($locale, config('festilaw.supported_locales'), true);

        if ($supported) {
            $request->session()->put('locale', $locale);
        }

        // Retour SAME-ORIGIN uniquement : le Referer est controle par le visiteur, on ne redirige
        // jamais vers un hote externe (pas d'open redirect). A defaut, on renvoie a l'accueil.
        $previous = url()->previous();
        $sameOrigin = str_starts_with($previous, $request->schemeAndHttpHost().'/') || $previous === $request->schemeAndHttpHost();

        if (! $sameOrigin) {
            return redirect()->to($localizedUrls->route('home', [], $supported ? $locale : null));
        }

        $target = $supported ? ($localizedUrls->translate($previous, $locale) ?? $previous) : $previous;

        return redirect()->to($target);
    }
}
