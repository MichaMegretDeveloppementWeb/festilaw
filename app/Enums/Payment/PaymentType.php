<?php

declare(strict_types=1);

namespace App\Enums\Payment;

enum PaymentType: string
{
    case StarterSubscription = 'starter_subscription';
    case AnnualRenewal = 'annual_renewal';
    case ScaleAudit = 'scale_audit';
    // Passage du Creator au Pro apres paiement (SC12) : la difference au prorata des mois restants. Ce n'est
    // pas un abonnement (il ne rend pas un dossier actif et ne couvre pas d'annee de service).
    case PackUpgrade = 'pack_upgrade';

    /** Libelle lisible (back-office francophone). */
    public function label(): string
    {
        return match ($this) {
            self::StarterSubscription => __('Abonnement (année 1)'),
            self::AnnualRenewal => __('Renouvellement annuel'),
            self::ScaleAudit => __('Audit Scale'),
            self::PackUpgrade => __('Passage au Pro'),
        };
    }

    /** Paiements de la cotisation annuelle de Personne Responsable (annee 1 + renouvellements). */
    public function isSubscription(): bool
    {
        return in_array($this, self::subscriptionCases(), true);
    }

    /**
     * Types d'abonnement (annee 1 + renouvellements), pour les requetes `whereIn('type', ...)`.
     *
     * @return array<int, self>
     */
    public static function subscriptionCases(): array
    {
        return [self::StarterSubscription, self::AnnualRenewal];
    }
}
