<?php

namespace Tests\Feature;

use App\Models\Departament;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_it_checks_in_once_per_day(): void
    {
        $this->assertNotNull(Departament::query()->firstOrCreate(['name' => 'Sin departamento']));

        $user = User::factory()->create();
        $service = new AttendanceService();

        $attendance = $service->checkIn($user, Carbon::parse('2026-09-08 08:45'));

        $this->assertNotNull($attendance->check_in_at);
        $this->assertSame('2026-09-08', $attendance->date->toDateString());

        $this->expectException(ValidationException::class);
        $service->checkIn($user, Carbon::parse('2026-09-08 09:15'));
    }

    public function test_it_validates_lunch_and_checkout_sequence(): void
    {
        $user = User::factory()->create();
        $service = new AttendanceService();

        $this->expectException(ValidationException::class);
        $service->startLunch($user, Carbon::parse('2026-09-08 12:00'));

        $attendance = $service->checkIn($user, Carbon::parse('2026-09-08 08:50'));
        $service->startLunch($attendance, Carbon::parse('2026-09-08 12:00'));
        $service->endLunch($attendance, Carbon::parse('2026-09-08 13:00'));

        $this->assertNotNull($attendance->fresh()->lunch_start_at);
        $this->assertNotNull($attendance->fresh()->lunch_end_at);

        $service->checkOut($attendance, Carbon::parse('2026-09-08 17:10'));

        $this->assertNotNull($attendance->fresh()->check_out_at);
        $this->assertGreaterThan(0, $attendance->fresh()->total_minutes);
    }
}
