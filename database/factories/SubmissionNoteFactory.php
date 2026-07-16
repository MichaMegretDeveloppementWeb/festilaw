<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Submission;
use App\Models\SubmissionNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionNote>
 */
class SubmissionNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'author_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
