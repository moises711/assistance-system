<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WorkSiteFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Sede de prueba', 'latitude' => -12.0464, 'longitude' => -77.0428, 'radius_meters' => 100, 'capture_accuracy' => 5, 'captured_at' => now(), 'is_active' => true];
    }
}
