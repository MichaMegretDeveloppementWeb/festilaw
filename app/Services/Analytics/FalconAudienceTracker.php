<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Contracts\Analytics\AudienceTracker;
use App\Enums\Analytics\AudienceEvent;
use Falcon\Analytics\Facades\Analytics;
use Illuminate\Contracts\Session\Session;

/**
 * Le suivi d'audience par Falcon Analytics. Le package differe l'ecriture apres la reponse, ne leve jamais
 * rien, et n'enregistre rien quand la mesure est coupee, quand le visiteur est exclu (compte du back-office
 * connecte) ou quand la requete vient d'un robot. Les points et la qualite de conversion viennent de la
 * declaration (app/Analytics/events.php) : aucune occurrence ne porte les siens. Un fait passe avec une reference
 * de visite (paiement confirme plus tard par Stripe) entre dans cette visite, a la date de la reference.
 */
final readonly class FalconAudienceTracker implements AudienceTracker
{
    /** Cle de session des faits deja comptes « une fois » pour ce visiteur. */
    private const RECORDED_KEYS = 'audience.recorded';

    public function __construct(private Session $session) {}

    public function record(AudienceEvent $event, ?string $session = null): void
    {
        Analytics::record($event->value, session: $session);
    }

    public function currentSession(): ?string
    {
        return Analytics::currentSession();
    }

    public function recordOnce(AudienceEvent $event, string $key): void
    {
        $recorded = (array) $this->session->get(self::RECORDED_KEYS, []);
        $onceKey = $event->value.'|'.$key;

        if (in_array($onceKey, $recorded, true)) {
            return;
        }

        $this->session->put(self::RECORDED_KEYS, [...$recorded, $onceKey]);
        $this->record($event);
    }
}
