<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\PackChange;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackChange>
 */
class PackChangeFactory extends Factory
{
    /**
     * Par defaut : une montee Creator -> Pro en cours.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory()->starter(),
            'from_pack' => SubmissionType::Starter,
            'to_pack' => SubmissionType::Pro,
            'status' => PackChangeStatus::Open,
        ];
    }

    /** Retour Pro -> Creator demande par le client, en attente de Festilaw. */
    public function downgradeRequested(): static
    {
        return $this->state(fn (): array => [
            'from_pack' => SubmissionType::Pro,
            'to_pack' => SubmissionType::Starter,
            'status' => PackChangeStatus::Requested,
            'eligibility_confirmed_at' => now(),
        ]);
    }
}
