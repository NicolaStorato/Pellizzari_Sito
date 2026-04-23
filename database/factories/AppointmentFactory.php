<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduledAt = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'patient_id' => User::factory()->patient(),
            'doctor_id' => User::factory()->doctor(),
            'scheduled_at' => $scheduledAt,
            'status' => Appointment::STATUS_PENDING,
            'patient_notes' => fake()->optional()->sentence(),
            'doctor_notes' => null,
            'confirmed_at' => null,
            'cancelled_at' => null,
        ];
    }
}
