<?php

namespace Database\Factories;

use App\Enums\Appointment\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory()->scale(),
            'status' => AppointmentStatus::Requested,
        ];
    }
}
