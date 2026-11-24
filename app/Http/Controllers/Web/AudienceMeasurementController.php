<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AudienceMeasurementChoice;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Le bouton de la politique de confidentialite qui refuse ou accepte de nouveau la mesure d'audience. Le
 * choix vit dans un cookie ; on revient au meme passage de la politique, dans la langue du bouton, sans jamais
 * suivre l'adresse precedente (le Referer est controle par le visiteur).
 */
final class AudienceMeasurementController extends Controller
{
    public function __invoke(Request $request, AudienceMeasurementChoice $choice, LocalizedUrlGenerator $localizedUrls): RedirectResponse
    {
        $validated = $request->validate([
            'choice' => ['required', Rule::in(['refuse', 'accept'])],
            'locale' => ['required', Rule::in(config('festilaw.supported_locales'))],
        ]);

        $response = redirect()->to($localizedUrls->route('privacy-policy', [], $validated['locale']).'#audience-measurement');

        if ($validated['choice'] === 'refuse') {
            foreach ($choice->refusalCookies() as $cookie) {
                $response->withCookie($cookie);
            }

            return $response;
        }

        return $response->withCookie($choice->acceptanceCookie());
    }
}
