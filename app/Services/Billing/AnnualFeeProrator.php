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
    /** Whole months from the reference month through December (inclusive): January = 12, December = 1. */
    public function remainingMonths(CarbonInterface $reference): int
    {
        return 13 - $reference->month;
    }

    /** The first-year fee in cents: the annual fee prorated over the months left until 31 December. */
    public function firstYearCents(int $annualCents, CarbonInterface $reference): int
    {
        return (int) round($annualCents * $this->remainingMonths($reference) / 12);
    }
}
