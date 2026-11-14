<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogGenerationType;
use Closure;

/**
 * Pipeline de test : des etapes dont le test decide le comportement (reussir, echouer, s'arreter), et qui
 * comptent leurs appels. Sert a exercer le moteur de generation sans aucun modele ni attente.
 */
final class FakeGenerationPipeline implements GenerationPipeline
{
    /** Cout de chaque etape qui reussit sans comportement particulier (millioniemes de dollar). */
    public const STEP_COST = 10_000;

    /** @var array<string, int> nombre d'executions par etape */
    public array $calls = [];

    /** @var array<string, Closure(GenerationStepContext): GenerationStepResult> */
    private array $behaviours = [];

    /** @param  list<string>  $keys */
    private function __construct(private array $keys, private int $timeoutSeconds) {}

    /**
     * Installe le pipeline de test a la place de celui de la configuration.
     *
     * @param  list<string>  $keys
     */
    public static function bind(array $keys = ['topic', 'writing', 'assembly'], int $timeoutSeconds = 30): self
    {
        $pipeline = new self($keys, $timeoutSeconds);

        app()->instance(GenerationPipeline::class, $pipeline);

        return $pipeline;
    }

    /** @param  Closure(GenerationStepContext): GenerationStepResult  $behaviour */
    public function on(string $key, Closure $behaviour): self
    {
        $this->behaviours[$key] = $behaviour;

        return $this;
    }

    public function calls(string $key): int
    {
        return $this->calls[$key] ?? 0;
    }

    public function steps(BlogGenerationType $type): array
    {
        return array_map(fn (string $key): GenerationStep => new class($key, $this->timeoutSeconds, fn (GenerationStepContext $context): GenerationStepResult => $this->run($key, $context)) implements GenerationStep
        {
            public function __construct(private string $key, private int $timeoutSeconds, private Closure $run) {}

            public function key(): string
            {
                return $this->key;
            }

            public function timeoutSeconds(): int
            {
                return $this->timeoutSeconds;
            }

            public function execute(GenerationStepContext $context): GenerationStepResult
            {
                return ($this->run)($context);
            }
        }, $this->keys);
    }

    private function run(string $key, GenerationStepContext $context): GenerationStepResult
    {
        $this->calls[$key] = $this->calls($key) + 1;

        if (isset($this->behaviours[$key])) {
            return ($this->behaviours[$key])($context);
        }

        return GenerationStepResult::continue(['step' => $key], new GenerationUsage(
            model: 'test/model',
            inputTokens: 100,
            outputTokens: 50,
            costMicrousd: self::STEP_COST,
        ));
    }
}
