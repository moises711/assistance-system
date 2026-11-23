<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function employee(bool $required = false): User
    {
        return $this->assignAttendanceSite(User::factory()->create(['api_token' => 'employee-token', 'require_activity_report' => $required]));
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer employee-token'];
    }

    public function test_activity_is_only_required_at_exit_including_qr(): void
    {
        $user = $this->employee(true);
        $this->getJson('/api/v1/me', $this->headers())->assertOk()->assertJsonPath('require_activity_report', true);
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertCreated();
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition()], $this->headers())->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->postJson('/api/v1/attendance/scan', ['qr_data' => config('app.attendance_qr_key')], $this->headers())
            ->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->assertDatabaseCount('attendances', 1);
        $this->assertNull(Attendance::firstOrFail()->check_out_at);
        $this->postJson('/api/v1/attendance/scan', ['qr_data' => config('app.attendance_qr_key'), 'notes' => 'Inventario realizado'], $this->headers())
            ->assertOk()->assertJsonPath('attendance.daily_activity', 'Inventario realizado')->assertJsonPath('action', 'check_out');
        $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'daily_activity' => 'Inventario realizado']);
    }

    public function test_optional_activity_and_duplicate_entry(): void
    {
        $this->employee();
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertCreated();
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 1);
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition()], $this->headers())->assertOk();
    }

    public function test_lunch_order_and_eight_worked_hours(): void
    {
        $this->employee();
        $this->travelTo(now()->setTime(8, 0));
        $this->postJson('/api/v1/attendance/lunch/start', [], $this->headers())->assertUnprocessable();
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertCreated();
        $this->travelTo(now()->setTime(12, 0));
        $this->postJson('/api/v1/attendance/lunch/start', [], $this->headers())->assertOk();
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition()], $this->headers())->assertUnprocessable();
        $this->travelTo(now()->setTime(13, 0));
        $this->postJson('/api/v1/attendance/lunch/end', [], $this->headers())->assertOk();
        $this->travelTo(now()->setTime(17, 0));
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition()], $this->headers())->assertOk()->assertJsonPath('attendance.total_minutes', 480);
        $this->postJson('/api/v1/attendance/lunch/start', [], $this->headers())->assertUnprocessable();
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition()], $this->headers())->assertUnprocessable();
    }

    public function test_tardiness_and_overnight_shift(): void
    {
        $user = $this->employee();
        $user->schedule->update(['start_time' => '22:00', 'end_time' => '06:00', 'tolerance_minutes' => 10]);
        $this->travelTo(now()->setTime(22, 30));
        $date = now()->toDateString();
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertCreated()
            ->assertJsonPath('attendance.late_minutes', 20)->assertJsonPath('attendance.status', 'tarde');
        $this->travelTo(now()->addDay()->setTime(6, 0));
        $this->getJson('/api/v1/attendance/today', $this->headers())->assertOk()->assertJsonPath('user_id', $user->id);
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition()], $this->headers())->assertOk()->assertJsonPath('attendance.total_minutes', 450);
        $this->assertSame($date, Attendance::where('user_id', $user->id)->firstOrFail()->date->toDateString());
        $this->assertDatabaseCount('attendances', 1);
        $this->getJson('/api/v1/attendance/today', $this->headers())->assertOk()->assertJsonPath('user_id', $user->id);
    }

    public function test_other_users_records_and_global_scope_are_forbidden(): void
    {
        $this->employee();
        $other = Attendance::factory()->create();
        $this->getJson('/api/v1/attendances/'.$other->id, $this->headers())->assertForbidden();
        $this->getJson('/api/v1/attendances?scope=all', $this->headers())->assertForbidden();
        $this->getJson('/api/v1/attendances', $this->headers())->assertOk()->assertExactJson([]);
    }

    public function test_login_logout_and_expiration(): void
    {
        $user = $this->employee();
        $response = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $headers = ['Authorization' => 'Bearer '.$response->json('token')];
        $this->postJson('/api/v1/logout', [], $headers)->assertOk();
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
        $user->forceFill(['api_token' => 'expired', 'api_token_expires_at' => now()->subMinute()])->save();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer expired'])->assertUnauthorized();
    }

    public function test_activity_backfill_preserves_existing_values(): void
    {
        $legacy = Attendance::factory()->create();
        $current = Attendance::factory()->create();
        DB::table('attendances')->where('id', $legacy->id)
            ->update(['daily_activity' => null, 'Activity_description' => 'Actividad antigua', 'status' => 'present']);
        DB::table('attendances')->where('id', $current->id)
            ->update(['daily_activity' => 'Actividad actual', 'Activity_description' => 'Texto anterior']);
        $migration = require database_path('migrations/2026_10_10_152500_backfill_daily_activity_and_normalize_attendance_status.php');
        $migration->up();
        $this->assertSame('Actividad antigua', $legacy->fresh()->daily_activity);
        $this->assertSame('presente', $legacy->fresh()->status);
        $this->assertSame('Actividad actual', $current->fresh()->daily_activity);
        $this->assertSame('Texto anterior', $current->fresh()->getRawOriginal('Activity_description'));
    }
}
