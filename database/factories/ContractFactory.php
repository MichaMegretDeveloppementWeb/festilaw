<?php

namespace Database\Factories;

use App\Enums\Contract\SignatureStatus;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory()->starter(),
            'filled_fields' => [],
            'signature_status' => SignatureStatus::Pending,
        ];
    }

    public function signed(): static
    {
        return $this->state(fn (): array => [
            'signature_status' => SignatureStatus::Signed,
            'signature_provider' => 'fake',
            'signature_provider_reference' => fake()->uuid(),
            'signed_file_path' => 'private/contracts/'.fake()->uuid().'.pdf',
            'signed_at' => now(),
        ]);
    }
}
