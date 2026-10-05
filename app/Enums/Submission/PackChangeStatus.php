<?php

declare(strict_types=1);

namespace App\Enums\Submission;

/**
 * Etat d'un changement de pack apres paiement (SC12).
 *
 * Montee Creator -> Pro (le client seul) : Open (mandat Pro a signer puis difference a payer) -> Completed ;
 * Cancelled (abandonnee par le client avant paiement) ; Reverted (annulee et remboursee par Festilaw).
 *
 * Retour Pro -> Creator (valide par Festilaw) : Requested -> Approved (effet au renouvellement) -> Applied ;
 * Rejected (refusee par Festilaw) ; Withdrawn (retiree par le client) ; Voided (sans objet : le passage au Pro
 * qui l'avait precedee a ete annule et rembourse, le dossier est deja revenu au Creator).
 */
enum PackChangeStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Reverted = 'reverted';
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Applied = 'applied';
    case Voided = 'voided';

    /** Libelle back-office (francophone). */
    public function label(): string
    {
        return match ($this) {
            self::Open => __('En cours'),
            self::Completed => __('Effectué'),
            self::Cancelled => __('Abandonné par le client'),
            self::Reverted => __('Annulé et remboursé'),
            self::Requested => __('À valider'),
            self::Approved => __('Validé'),
            self::Rejected => __('Refusé'),
            self::Withdrawn => __('Retiré par le client'),
            self::Applied => __('Appliqué'),
            self::Voided => __('Sans objet (passage au Pro annulé)'),
        };
    }

    /**
     * Changements encore en cours : un seul a la fois par dossier.
     *
     * @return array<int, self>
     */
    public static function inProgress(): array
    {
        return [self::Open, self::Requested, self::Approved];
    }
}
