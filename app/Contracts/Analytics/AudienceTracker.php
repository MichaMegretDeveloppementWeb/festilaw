<?php

declare(strict_types=1);

namespace App\Contracts\Analytics;

use App\Enums\Analytics\AudienceEvent;

/**
 * Le suivi d'audience vu par Festilaw. Un fait va au visiteur de la requete en cours. Un fait constate sans lui
 * (webhook, commande, back-office) passe la reference de visite gardee plus tot, que le package n'emploie que si
 * la requete n'a pas son propre visiteur, et le fait prend la date de cette reference. Sans visiteur ni
 * reference, rien ne se compte : un webhook servi avec une session compterait sinon un faux visiteur. Ne leve
 * jamais rien.
 */
interface AudienceTracker
{
    public function record(AudienceEvent $event, ?string $session = null): void;

    /**
     * Enregistre le fait une seule fois pour ce visiteur, sous la cle donnee : un second passage par la
     * meme adresse (bouton Precedent sur le retour d'un paiement) ne le compte pas deux fois.
     */
    public function recordOnce(AudienceEvent $event, string $key): void;

    /**
     * La reference scellee de la visite en cours, a garder pour un fait constate plus tard sans le visiteur.
     * Nulle sans visite mesuree (mesure eteinte, compte du back-office, robot, visite inactive).
     */
    public function currentSession(): ?string;
}
