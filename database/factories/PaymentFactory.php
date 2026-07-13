<?php

namespace Database\Factories;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Models\Payment;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'type' => PaymentType::StarterSubscription,
            'amount_cents' => 33300,
            'currency' => 'EUR',
            'provider' => 'fake',
            'status' => PaymentStatus::Pending,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Succeeded,
            'provider_reference' => fake()->uuid(),
            'paid_at' => now(),
        ]);
    }
}
