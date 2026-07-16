<?php

namespace Database\Factories;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SubmissionType::Contact,
            'status' => SubmissionStatus::New,
            'locale' => 'en',
            'email' => fake()->safeEmail(),
            'first_name' => fake()->firstName(),
            'message' => fake()->sentence(),
        ];
    }

    public function starter(): static
    {
        return $this->state(fn (): array => [
            'type' => SubmissionType::Starter,
            'status' => SubmissionStatus::InProgress,
            'company_name' => fake()->company(),
            'last_name' => fake()->lastName(),
            'website_url' => 'https://'.fake()->domainName(),
            'message' => null,
            'resume_token' => Str::random(48),
            'resume_expires_at' => now()->addDays(30),
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (): array => [
            'type' => SubmissionType::Pro,
            'company_name' => fake()->company(),
            'message' => null,
        ]);
    }

    public function scale(): static
    {
        return $this->state(fn (): array => [
            'type' => SubmissionType::Scale,
            'company_name' => fake()->company(),
            'message' => null,
        ]);
    }
}
