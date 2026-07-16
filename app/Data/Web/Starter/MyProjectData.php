<?php

declare(strict_types=1);

namespace App\Data\Web\Starter;

use Illuminate\Support\Carbon;

/**
 * View-model de l'espace client "mon projet" : contrat de sortie type entre le controleur et la vue,
 * pour qu'aucun modele Eloquent brut ne soit passe a la vue. Ne contient que ce que la vue affiche.
 */
final readonly class MyProjectData
{
    /** @param  list<ProjectDocumentData>  $documents */
    public function __construct(
        public string $reference,
        public bool $signed,
        public bool $documentsDone,
        public bool $paid,
        public bool $cancelled,
        public ?Carbon $renewsAt,
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
