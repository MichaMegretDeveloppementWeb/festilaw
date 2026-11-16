<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use Carbon\CarbonImmutable;

/**
 * La vue d'ensemble de la generation par IA : ou en est la depense du mois, combien de brouillons generes
 * attendent une relecture, les dernieres generations, et si la generation peut tourner (cle presente, worker
 * a l'ecoute, pipeline utilisable). La cle elle-meme n'est jamais exposee.
 */
final readonly class BlogGenerationOverview
{
    /** @param  list<BlogGenerationJournalRow>  $latest  les dernieres generations, la plus recente d'abord */
    public function __construct(
        public BlogAiSettings $settings,
        public string $automaticRhythm,
        public string $spent,
        public string $cap,
        public int $spentPercent,
        public bool $capReached,
        public int $pendingDrafts,
        public array $latest,
        public bool $apiKeyPresent,
        public bool $workerListening,
        public ?CarbonImmutable $workerStartedAt,
        public bool $simulated,
        public ?string $unavailableReason,
    ) {}

    /** Rien n'empeche de lancer une generation maintenant. */
    public function canLaunch(): bool
    {
        return $this->unavailableReason === null && ! $this->capReached;
    }
}
