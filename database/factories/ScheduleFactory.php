<?php

namespace Database\Factories;

use App\Enums\ScheduleType;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'name' => 'Jornada laboral',
            'type' => ScheduleType::Fixed,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
            'maximum_late_minutes' => 120,
            'allows_late_entry' => false,
            'working_days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ];
    }
}
