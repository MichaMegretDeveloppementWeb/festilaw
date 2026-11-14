<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use Carbon\CarbonImmutable;

/**
 * Le haut de l'ecran « Generation IA » : ou en est la depense du mois, et si la generation peut tourner
 * (cle presente, worker a l'ecoute, pipeline utilisable). La cle elle-meme n'est jamais exposee.
 */
final readonly class BlogGenerationOverview
{
    public function __construct(
        public BlogAiSettings $settings,
        public string $automaticRhythm,
        public string $spent,
        public string $cap,
        public int $spentPercent,
        public bool $capReached,
        public bool $apiKeyPresent,
        public bool $workerListening,
        public ?CarbonImmutable $workerStartedAt,
        public bool $simulated,
        public ?string $unavailableReason,
    ) {}
}
