<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Models\BlogGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogGeneration>
 */
class BlogGenerationFactory extends Factory
{
    /**
     * Par defaut : une generation d'article demandee au back-office, en attente, seule dans son lot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => BlogGenerationType::Article,
            'trigger' => BlogGenerationTrigger::Manual,
            'status' => BlogGenerationStatus::Queued,
            'batch_uuid' => (string) Str::uuid(),
            // Toute generation recoit son cout annonce a sa creation (file, lot planifie, relance).
            'forecast_low_microusd' => 200_000,
            'forecast_high_microusd' => 320_000,
        ];
    }

    /** Creee par la tache planifiee. */
    public function scheduled(): static
    {
        return $this->state(fn (): array => ['trigger' => BlogGenerationTrigger::Scheduled]);
    }

    /** Dans le lot donne (plusieurs generations d'un meme lancement). */
    public function inBatch(string $batchUuid): static
    {
        return $this->state(fn (): array => ['batch_uuid' => $batchUuid]);
    }

    /** Prise en charge par un worker, verrou encore valable. */
    public function running(string $owner = 'worker-test'): static
    {
        return $this->state(fn (): array => [
            'status' => BlogGenerationStatus::Running,
            'started_at' => now()->subMinute(),
            'locked_until' => now()->addMinutes(5),
            'lock_owner' => $owner,
        ]);
    }

    /** Prise en charge puis abandonnee par un worker mort : le verrou a expire. */
    public function interrupted(): static
    {
        return $this->state(fn (): array => [
            'status' => BlogGenerationStatus::Running,
            'started_at' => now()->subMinutes(10),
            'locked_until' => now()->subMinute(),
            'lock_owner' => 'dead-worker',
        ]);
    }

    public function completed(int $costMicrousd = 250_000): static
    {
        return $this->state(fn (): array => [
            'status' => BlogGenerationStatus::Completed,
            'started_at' => now()->subMinutes(6),
            'finished_at' => now(),
            'cost_microusd' => $costMicrousd,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => BlogGenerationStatus::Failed,
            'outcome_reason' => BlogGenerationOutcomeReason::StepFailed,
            'started_at' => now()->subMinutes(3),
            'finished_at' => now(),
        ]);
    }

    public function abandoned(BlogGenerationOutcomeReason $reason = BlogGenerationOutcomeReason::NoRelevantTopic): static
    {
        return $this->state(fn (): array => [
            'status' => BlogGenerationStatus::Abandoned,
            'outcome_reason' => $reason,
            'finished_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => BlogGenerationStatus::Cancelled,
            'outcome_reason' => BlogGenerationOutcomeReason::CancelledByUser,
            'finished_at' => now(),
        ]);
    }
}
