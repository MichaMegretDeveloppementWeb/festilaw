<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog\Generation;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Admin\Blog\Generation\BlogGenerationOverview;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogGenerationException;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationBudget;
use App\Services\Blog\Generation\BlogGenerationWorkerRegistry;
use App\Services\Blog\Generation\Simulation\SimulatedGenerationPipeline;
use Carbon\CarbonImmutable;

/**
 * La vue d'ensemble de la generation par IA.
 *
 * Plan de requetes (verifie par un test de budget) : 6.
 *   1. reglages
 *   2. depense du mois
 *   3. inscription du dernier worker
 *   4. brouillons generes qui attendent une relecture
 *   5. dernieres generations
 *   6. etape en echec de ces generations
 * Sans aucune generation : 5 (la sixieme n'a rien a lire).
 */
final readonly class BlogGenerationOverviewService
{
    /** Nombre de generations montrees sur la vue d'ensemble ; le reste est dans l'historique. */
    public const LATEST = 5;

    /**
     * Le planificateur relance un worker chaque minute : passe ce delai sans nouvelle inscription, plus
     * aucun n'est a l'ecoute.
     */
    private const WORKER_SILENCE_SECONDS = 150;

    public function __construct(
        private BlogAiSettingsService $settings,
        private BlogGenerationBudget $budget,
        private BlogGenerationWorkerRegistry $workers,
        private BlogGenerationJournalService $journal,
        private BlogAiCostFormatter $costs,
        private GenerationPipeline $pipeline,
        private BlogGenerationPresenter $presenter,
        private ?string $apiKey,
    ) {}

    public function overview(): BlogGenerationOverview
    {
        $settings = $this->settings->current();
        $spent = $this->budget->spentThisMonthMicrousd();
        $cap = $settings->monthlySpendCapMicrousd();
        $workerStartedAt = $this->workers->lastStartedAt();

        return new BlogGenerationOverview(
            settings: $settings,
            automaticRhythm: $this->presenter->rhythm($settings->articlesPerRun, $settings->periodCount, $settings->periodUnit),
            spent: $this->costs->dollars($spent),
            cap: $this->costs->dollars($cap),
            spentPercent: $cap > 0 ? min(100, (int) round(100 * $spent / $cap)) : 100,
            monthlyWarning: $this->presenter->monthlyWarning($this->budget->monthlyLevel($spent, $cap), $spent, $cap),
            pendingDrafts: $this->budget->pendingDrafts(),
            latest: $this->journal->latest(self::LATEST),
            apiKeyPresent: filled($this->apiKey),
            workerListening: $workerStartedAt !== null
                && CarbonImmutable::now()->getTimestamp() - $workerStartedAt->getTimestamp() <= self::WORKER_SILENCE_SECONDS,
            workerStartedAt: $workerStartedAt,
            simulated: $this->pipeline instanceof SimulatedGenerationPipeline,
            unavailableReason: $this->unavailableReason(),
        );
    }

    /** Pourquoi la generation ne peut pas tourner (configuration du pipeline), ou nul si elle le peut. */
    private function unavailableReason(): ?string
    {
        try {
            $this->pipeline->steps(BlogGenerationType::Article);

            return null;
        } catch (BlogGenerationException $e) {
            return $e->getUserMessage();
        }
    }
}
