<?php

declare(strict_types=1);

namespace App\Contracts\Analytics;

use App\Enums\Analytics\AudienceEvent;

/**
 * Le suivi d'audience vu par Festilaw : un fait du parcours, enregistre pour le visiteur de la requete en
 * cours. Il ne s'appelle que depuis un bord public (composant Livewire ou controleur du site), apres la
 * reussite de l'Action : un webhook, une commande ou une tache n'ont pas de visiteur, et un webhook servi
 * avec une session compterait un faux visiteur. Ne leve jamais rien.
 */
interface AudienceTracker
{
    public function record(AudienceEvent $event): void;

    /**
     * Enregistre le fait une seule fois pour ce visiteur, sous la cle donnee : un second passage par la
     * meme adresse (bouton Precedent sur le retour d'un paiement) ne le compte pas deux fois.
     */
    public function recordOnce(AudienceEvent $event, string $key): void;
}
