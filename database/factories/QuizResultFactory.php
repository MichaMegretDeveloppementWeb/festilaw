<?php

namespace Database\Factories;

use App\Enums\Quiz\QuizOutcome;
use App\Models\QuizResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizResult>
 */
class QuizResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'q1_based_outside_eu' => true,
            'q2_eu_countries' => ['ALL_27'],
            'q3_sells_restricted' => false,
            'outcome' => QuizOutcome::Concerned,
            'locale' => 'en',
        ];
    }
}
