<?php

namespace Database\Factories;

use App\Models\Dispenser;
use App\Models\SensorLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SensorLog>
 */
class SensorLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $temperature = fake()->randomFloat(2, 16, 30);
        $humidity = fake()->randomFloat(2, 35, 70);

        return [
            'dispenser_id' => Dispenser::factory(),
            'patient_id' => User::factory()->patient(),
            'temperature' => $temperature,
            'humidity' => $humidity,
            'threshold_exceeded' => $temperature > 25 || $humidity > 60,
            'threshold_violations' => null,
            'recorded_at' => now()->subMinutes(fake()->numberBetween(1, 1440)),
        ];
    }
}
