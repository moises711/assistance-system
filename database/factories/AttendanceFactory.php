<?php

namespace Database\Factories;

use App\Models\Departament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        $date = Carbon::parse(fake()->dateTimeBetween('-1 month', 'now'));
        $checkIn = (clone $date)->setTime(fake()->numberBetween(7, 9), fake()->numberBetween(0, 59));
        $checkOut = (clone $date)->setTime(fake()->numberBetween(16, 18), fake()->numberBetween(0, 59));
        
        $totalMinutes = $checkIn->diffInMinutes($checkOut);
        $lateMinutes = $checkIn->format('H:i') > '08:00' ? $checkIn->diffInMinutes((clone $date)->setTime(8, 0)) : 0;

        return [
            'user_id' => User::factory(),
            'department_id' => Departament::factory(),
            'date' => $date->toDateString(),
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'total_minutes' => $totalMinutes,
            'late_minutes' => $lateMinutes,
            'status' => $lateMinutes > 0 ? 'tarde' : 'presente',
            'check_in_method' => fake()->randomElement(['web', 'qr']),
            'check_out_method' => fake()->randomElement(['web', 'qr']),
        ];
    }
}