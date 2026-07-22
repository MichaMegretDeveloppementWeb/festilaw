<?php

declare(strict_types=1);

namespace App\Data\Web\Scale;

use Carbon\CarbonInterface;

/**
 * View-model de l'espace client SCALE : contrat de sortie entre le controleur et la vue, pour qu'aucun
 * modele Eloquent brut ne soit passe a la vue. Ne contient que ce que la vue affiche.
 */
final readonly class ScaleSpaceData
{
    public function __construct(
        public string $reference,
        public string $companyName,
        public bool $cancelled,
        public bool $auditPaid,
        public int $auditAmountCents,
        public ?CarbonInterface $paidAt,
        public bool $booked,
        public ?string $appointmentStatusLabel,
        public ?CarbonInterface $scheduledAt,
        public string $calendarUrl,
        public string $payUrl,
        public string $bookUrl,
    ) {}
}
