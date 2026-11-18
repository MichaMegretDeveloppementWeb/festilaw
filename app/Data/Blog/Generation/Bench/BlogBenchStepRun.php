<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation\Bench;

use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\GenerationUsage;
use Throwable;

/**
 * Une etape jouee par le banc : son resultat, ou l'erreur de son dernier essai, avec ce que tous ses essais
 * ont consomme (un essai rate a ete facture) et leur duree.
 */
final readonly class BlogBenchStepRun
{
    public function __construct(
        public ?GenerationStepResult $result,
        public GenerationUsage $usage,
        public int $attempts,
        public int $durationMs,
        public ?Throwable $error = null,
        public bool $overBudget = false,
        public bool $creditExhausted = false,
    ) {}

    public function succeeded(): bool
    {
        return $this->result !== null;
    }

    /**
     * Ce que garde le fichier de resultats pour cette etape.
     *
     * @return array<string, mixed>
     */
    public function record(): array
    {
        return [
            'model' => $this->usage->model,
            'cost_microusd' => $this->usage->costMicrousd,
            'cost_is_estimated' => $this->usage->costIsEstimated,
            'input_tokens' => $this->usage->inputTokens,
            'output_tokens' => $this->usage->outputTokens,
            'reasoning_tokens' => $this->usage->reasoningTokens,
            'web_searches' => $this->usage->webSearches,
            'duration_ms' => $this->durationMs,
            'attempts' => $this->attempts,
            'succeeded' => $this->succeeded(),
            'over_budget' => $this->overBudget,
            'summary' => $this->result?->summary,
        ];
    }
}
