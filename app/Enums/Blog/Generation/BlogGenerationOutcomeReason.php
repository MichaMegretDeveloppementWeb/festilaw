<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Pourquoi une generation s'est terminee sans article : la raison affichee au journal. Colonne sous
 * contrainte CHECK en production (cf. BlogGenerationStatus) : un cas nouveau exige une migration de
 * rafraichissement.
 */
enum BlogGenerationOutcomeReason: string
{
    case NoRelevantTopic = 'no_relevant_topic';
    case DuplicateTopic = 'duplicate_topic';
    case SpendCapReached = 'spend_cap_reached';
    case PendingDraftsCapReached = 'pending_drafts_cap_reached';
    case CreditExhausted = 'credit_exhausted';
    case StepFailed = 'step_failed';
    case CancelledByUser = 'cancelled_by_user';

    public function label(): string
    {
        return match ($this) {
            self::NoRelevantTopic => __('Aucun sujet assez pertinent'),
            self::DuplicateTopic => __('Sujet déjà traité ou refusé'),
            self::SpendCapReached => __('Plafond de dépense du mois atteint'),
            self::PendingDraftsCapReached => __('Trop de brouillons générés en attente de relecture'),
            self::CreditExhausted => __('Crédit épuisé chez le fournisseur d\'IA'),
            self::StepFailed => __('Une étape a échoué après plusieurs essais'),
            self::CancelledByUser => __('Arrêtée avant la fin'),
        };
    }
}
