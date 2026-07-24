<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Carbon\CarbonInterface;

/**
 * Prorates an annual fee for the first (partial) service year. The service year runs 1 January to
 * 31 December; year one is billed from the signature month through December, in whole months
 * (e.g. signing in July bills 6/12). From the second year on, the full annual fee applies.
 */
final class AnnualFeeProrator
{
    /**
     * @param  int  $minChargeCents  Plancher d'encaissement (defaut 0,50 € = minimum Stripe EUR). Injecte
     *                               depuis la config au binding (cf. AppServiceProvider) ; defaut ici pour
     *                               rester un pur objet instanciable sans le conteneur (tests unitaires).
     */
    public function __construct(private int $minChargeCents = 50) {}

    /** Whole months from the reference month through December (inclusive): January = 12, December = 1. */
    public function remainingMonths(CarbonInterface $reference): int
    {
        return 13 - $reference->month;
    }

    /** The first-year fee in cents: the annual fee prorated over the months left until 31 December. */
    public function firstYearCents(int $annualCents, CarbonInterface $reference): int
    {
        $prorated = (int) round($annualCents * $this->remainingMonths($reference) / 12);

        // Plancher : ne jamais facturer moins que le minimum encaissable par le prestataire (Stripe
        // ~0,50 €), sinon le checkout echoue. Ne mord qu'un tarif de test tres bas ; l'usage normal
        // (Creator 333 € / Pro 1200 €) reste tres au-dessus, meme proratise sur un seul mois.
        return max($prorated, $this->minChargeCents);
    }
}
