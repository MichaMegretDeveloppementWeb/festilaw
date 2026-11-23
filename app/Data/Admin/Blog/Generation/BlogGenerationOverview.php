<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\Health\BlogGenerationHealth;
use App\Enums\Blog\Generation\MonthlySpendLevel;
use Carbon\CarbonImmutable;

/**
 * La vue d'ensemble de la generation par IA : ou en est la depense du mois et son avertissement, combien de brouillons generes
 * attendent une relecture, les dernieres generations, et si la generation peut tourner (cle OpenRouter utilisable,
 * worker a l'ecoute, pipeline utilisable). La cle elle-meme n'est jamais exposee, seulement son debut et sa fin.
 */
final readonly class BlogGenerationOverview
{
    /**
     * @param  array{level: MonthlySpendLevel, title: string, text: string}|null  $monthlyWarning  l'avertissement du plafond du mois, nul en dessous du seuil
     * @param  list<BlogGenerationJournalRow>  $latest  les dernieres generations, la plus recente d'abord
     * @param  string|null  $nextLaunch  quand partira le prochain lot automatique, en une phrase ; nul quand la
     *                                   generation automatique est desactivee ou en pause
     */
    public function __construct(
        public BlogAiSettings $settings,
        public string $automaticRhythm,
        public string $spent,
        public string $cap,
        public int $spentPercent,
        public ?array $monthlyWarning,
        public int $pendingDrafts,
        public ?string $nextLaunch,
        public array $latest,
        public OpenRouterKeyState $key,
        public bool $workerListening,
        public ?CarbonImmutable $workerStartedAt,
        public bool $simulated,
        public ?string $unavailableReason,
        public ?BlogGenerationHealth $health = null,
        public bool $rulesReferenceDue = false,
    ) {}

    /** La generation automatique est activee, mais retenue tant que les brouillons a relire atteignent leur plafond. */
    public function automaticPaused(): bool
    {
        return $this->settings->enabled && $this->pendingDrafts >= $this->settings->pendingDraftsCap;
    }

    /** Les couleurs que la barre du kit connait : ambre a l'approche du plafond du mois, rouge au-dela. */
    public function spendBarColor(): string
    {
        return match ($this->monthlyWarning['level'] ?? null) {
            MonthlySpendLevel::Over => 'red',
            MonthlySpendLevel::Near => 'amber',
            default => 'indigo',
        };
    }
}
