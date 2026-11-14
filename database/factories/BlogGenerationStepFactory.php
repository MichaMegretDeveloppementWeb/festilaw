<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogGenerationStep>
 */
class BlogGenerationStepFactory extends Factory
{
    /**
     * Par defaut : la premiere etape d'une generation, pas encore commencee.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_generation_id' => BlogGeneration::factory(),
            'step' => 'topic',
            'position' => 1,
            'status' => BlogGenerationStepStatus::Pending,
        ];
    }

    public function named(string $step, int $position): static
    {
        return $this->state(fn (): array => ['step' => $step, 'position' => $position]);
    }

    /** Etape terminee, avec son resultat et sa consommation. */
    public function completed(int $costMicrousd = 30_000): static
    {
        return $this->state(fn (): array => [
            'status' => BlogGenerationStepStatus::Completed,
            'attempt' => 1,
            'model' => 'test/model',
            'input_tokens' => 1_200,
            'output_tokens' => 400,
            'cost_microusd' => $costMicrousd,
            'duration_ms' => 1_500,
            'output' => ['done' => true],
            'started_at' => now()->subSeconds(2),
            'finished_at' => now(),
        ]);
    }

    /** Etape en cours au moment ou le worker a ete coupe : son essai est deja compte. */
    public function running(int $attempt = 1): static
    {
        return $this->state(fn (): array => [
            'status' => BlogGenerationStepStatus::Running,
            'attempt' => $attempt,
            'started_at' => now()->subMinutes(5),
        ]);
    }

    public function failed(int $attempt = 3): static
    {
        return $this->state(fn (): array => [
            'status' => BlogGenerationStepStatus::Failed,
            'attempt' => $attempt,
            'error' => 'Simulated provider failure.',
            'started_at' => now()->subSeconds(2),
            'finished_at' => now(),
        ]);
    }
}
