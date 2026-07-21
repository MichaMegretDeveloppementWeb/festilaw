<?php

declare(strict_types=1);

namespace App\Enums\Submission;

/**
 * Etat AFFICHE d'un dossier (back-office + espace client), entierement DERIVE (paiements + renouvellement),
 * jamais stocke. Remplace l'ancien statut brut « Payé » qui ne refletait pas le renouvellement : un client
 * en retard n'est plus affiche « Payé ». Le statut stocke (SubmissionStatus) reste un marqueur de workflow
 * interne ; c'est cet etat-ci qui est montre et filtre.
 */
enum DossierState: string
{
    case InProgress = 'in_progress';
    case Active = 'active';
    case RenewalDue = 'renewal_due';
    case RenewalOverdue = 'renewal_overdue';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Libelle back-office / client (francophone). */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => __('En cours'),
            self::Active => __('Actif'),
            self::RenewalDue => __('À renouveler'),
            self::RenewalOverdue => __('En retard'),
            self::Completed => __('Terminé'),
            self::Cancelled => __('Annulé'),
        };
    }

    /** Severite pour la couleur du badge : ok (vert) · warn (ambre) · bad (rouge) · neutral · muted. */
    public function severity(): string
    {
        return match ($this) {
            self::Active => 'ok',
            self::RenewalDue => 'warn',
            self::RenewalOverdue => 'bad',
            self::InProgress => 'neutral',
            self::Completed => 'done',
            self::Cancelled => 'muted',
        };
    }
}
