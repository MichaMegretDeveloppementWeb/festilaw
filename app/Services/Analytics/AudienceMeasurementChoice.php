<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

/**
 * Le refus d'un visiteur d'etre compte dans la mesure d'audience (decision de Micha du 24/11/2026). La mesure
 * reconnait un navigateur d'une visite a l'autre sans lui demander son accord, ce que permet la dispense des
 * cookies de mesure d'audience, a condition que chacun puisse s'y opposer : c'est ce choix, propose dans la
 * politique de confidentialite. Il vit dans un cookie de treize mois, laisse en clair (sa valeur ne dit rien)
 * pour etre lu sur toutes les routes, celle du collecteur comprise (bootstrap/app.php).
 */
final readonly class AudienceMeasurementChoice
{
    public const COOKIE = 'festilaw_no_measurement';

    /** Le cookie qui reconnait le navigateur, pose par Falcon Analytics : il part avec le refus. */
    public const MEASUREMENT_COOKIE = 'fa_vid';

    /** Treize mois au plus, comme le cookie de mesure lui-meme. */
    private const LIFETIME_MINUTES = 60 * 24 * 395;

    public function isRefused(Request $request): bool
    {
        return $request->cookie(self::COOKIE) === '1';
    }

    /**
     * Les cookies qu'un refus pose et retire.
     *
     * @return list<HttpCookie>
     */
    public function refusalCookies(): array
    {
        return [
            Cookie::make(self::COOKIE, '1', self::LIFETIME_MINUTES),
            Cookie::forget(self::MEASUREMENT_COOKIE),
        ];
    }

    public function acceptanceCookie(): HttpCookie
    {
        return Cookie::forget(self::COOKIE);
    }
}
