<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Simulation;

use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Etape factice : elle attend quelques secondes et rend une consommation fictive, sans appeler aucun modele
 * ni rien ecrire. Sert a essayer le moteur, la reprise, les plafonds et les ecrans sans depenser un centime.
 */
final readonly class SimulatedGenerationStep implements GenerationStep
{
    /** Le reglage « modele par etape » dont depend chaque etape factice (aucun pour l'assemblage). */
    private const MODEL_SETTING = [
        'topic' => 'topic',
        'research' => 'research',
        'writing' => 'writing',
        'verification' => 'verification',
        'translation_fr' => 'translation',
        'translation_es' => 'translation',
        'images' => 'image',
    ];

    public function __construct(
        private string $key,
        private int $seconds,
        private int $costMicrousd,
        private bool $fails,
        private bool $stopsWithoutTopic,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function timeoutSeconds(): int
    {
        return $this->seconds + 30;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        Sleep::for($this->seconds)->seconds();

        if ($this->fails) {
            throw new RuntimeException("Simulated failure of step [{$this->key}] (attempt {$context->attempt}).");
        }

        if ($this->stopsWithoutTopic) {
            return GenerationStepResult::stop(BlogGenerationOutcomeReason::NoRelevantTopic, ['simulated' => true]);
        }

        $modelSetting = self::MODEL_SETTING[$this->key] ?? null;

        return GenerationStepResult::continue(
            ['simulated' => true, 'step' => $this->key],
            $modelSetting === null ? GenerationUsage::none() : new GenerationUsage(
                model: $context->settings->modelFor($modelSetting),
                inputTokens: 1_500,
                outputTokens: 600,
                costMicrousd: $this->costMicrousd,
                costIsEstimated: true,
            ),
        );
    }
}
