<?php

declare(strict_types=1);

namespace App\Data\Web\Starter;

use Carbon\CarbonInterface;

/**
 * View-model de l'espace client "mon projet" : contrat de sortie type entre le controleur et la vue,
 * pour qu'aucun modele Eloquent brut ne soit passe a la vue. Ne contient que ce que la vue affiche.
 */
final readonly class MyProjectData
{
    /** @param  list<ProjectDocumentData>  $documents */
    public function __construct(
        public string $reference,
        public string $packLabel,
        public int $annualCents,
        public bool $signed,
        public bool $documentsDone,
        public bool $paid,
        public bool $cancelled,
        public ?CarbonInterface $renewsAt,
        public bool $renewalDue,
        public bool $renewalOverdue,
        public ?int $renewalYear,
        public string $renewUrl,
        public ?int $paidAmountCents,
        public ?CarbonInterface $paidAt,
        public string $resumeUrl,
        public ?string $mandateDownloadUrl,
        public ?string $euRpAddress,
        public array $documents,
    ) {}

    public function hasDownloads(): bool
    {
        return $this->mandateDownloadUrl !== null || $this->documents !== [];
    }
}
