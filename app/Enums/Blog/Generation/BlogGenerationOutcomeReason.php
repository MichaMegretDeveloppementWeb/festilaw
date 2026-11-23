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
    case InsufficientSources = 'insufficient_sources';
    case PendingDraftsCapReached = 'pending_drafts_cap_reached';
    case CreditExhausted = 'credit_exhausted';
    case StepFailed = 'step_failed';
    case CancelledByUser = 'cancelled_by_user';
    /** Le cout allait depasser le double du haut annonce au lancement (decision de Micha du 18/11). */
    case SafetyStop = 'safety_stop';
    /** L'appel suivant n'est pas parti : son cout maximal ne tenait plus avant l'arret de securite (controle du 20/11). */
    case SafetyStopBeforeCall = 'safety_stop_before_call';
    /** Le fournisseur d'IA refuse la cle du site (HTTP 401, controle du 20/11). */
    case AiKeyRejected = 'ai_key_rejected';
    /** Le fournisseur d'IA ne propose plus le modele choisi pour une etape (controle du 20/11). */
    case AiModelUnavailable = 'ai_model_unavailable';

    public function label(): string
    {
        return match ($this) {
            self::NoRelevantTopic => __('Aucun sujet assez pertinent'),
            self::DuplicateTopic => __('Sujet déjà traité ou refusé'),
            self::InsufficientSources => __('Pas assez de sources fiables sur ce sujet'),
            self::PendingDraftsCapReached => __('Trop de brouillons générés en attente de relecture'),
            self::CreditExhausted => __('Crédit épuisé chez le fournisseur d\'IA'),
            self::StepFailed => __('Une étape a échoué après plusieurs essais'),
            self::CancelledByUser => __('Arrêtée avant la fin'),
            self::SafetyStop => __('Arrêtée par sécurité, coût bien au-delà de l\'estimation'),
            self::SafetyStopBeforeCall => __('Arrêtée par sécurité avant un appel trop coûteux'),
            self::AiKeyRejected => __('Clé du fournisseur d\'IA refusée'),
            self::AiModelUnavailable => __('Modèle retiré par le fournisseur d\'IA'),
        };
    }
}
