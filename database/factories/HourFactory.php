<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Hour;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hour>
 */
class HourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'activity_id' => Activity::factory(),
            'date' => fake()->date(),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ];
    }
}
