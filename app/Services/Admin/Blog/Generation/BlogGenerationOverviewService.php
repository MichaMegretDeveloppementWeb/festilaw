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
 * Le haut de l'ecran « Generation IA ».
 *
 * Plan de requetes (verifie par un test de budget) : 3.
 *   1. reglages
 *   2. depense du mois
 *   3. inscription du dernier worker
 */
final readonly class BlogGenerationOverviewService
{
    /**
     * Le planificateur relance un worker chaque minute : passe ce delai sans nouvelle inscription, plus
     * aucun n'est a l'ecoute.
     */
    private const WORKER_SILENCE_SECONDS = 150;

    public function __construct(
        private BlogAiSettingsService $settings,
        private BlogGenerationBudget $budget,
        private BlogGenerationWorkerRegistry $workers,
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
            capReached: $spent >= $cap,
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
