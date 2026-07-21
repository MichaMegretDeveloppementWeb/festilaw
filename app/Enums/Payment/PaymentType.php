<?php

declare(strict_types=1);

namespace App\Enums\Payment;

enum PaymentType: string
{
    case StarterSubscription = 'starter_subscription';
    case AnnualRenewal = 'annual_renewal';
    case ScaleAudit = 'scale_audit';

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
