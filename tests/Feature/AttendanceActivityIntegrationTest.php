<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\CreateActivity;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Hour;
use App\Models\User;
use App\Services\AttendanceService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceActivityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function employee(bool $required = true): User
    {
        return $this->assignAttendanceSite(User::factory()->create(['api_token' => 'employee', 'require_activity_report' => $required]));
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer employee'];
    }

    public function test_exit_creates_one_linked_activity_visible_to_its_owner(): void
    {
        $user = $this->employee();
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertCreated();
        $this->assertDatabaseCount('activities', 0);
        $description = str_repeat('Inventario de almacén y revisión. ', 30);
        $response = $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition(), 'notes' => $description], $this->headers())->assertOk();
        $id = $response->json('attendance.id');
        $this->assertDatabaseHas('activities', ['attendance_id' => $id, 'user_id' => $user->id, 'description' => trim($description), 'type' => 'work']);
        $this->getJson('/api/v1/activities?type=work&source=attendance&per_page=20', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.attendance_id', $id);
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition(), 'notes' => $description], $this->headers())->assertUnprocessable();
        $this->assertDatabaseCount('activities', 1);
    }

    public function test_qr_and_web_service_share_the_same_activity_integration(): void
    {
        $user = $this->employee();
        $this->postJson('/api/v1/attendance/scan', ['qr_data' => config('app.attendance_qr_key')], $this->headers())->assertCreated();
        $this->postJson('/api/v1/attendance/scan', ['qr_data' => config('app.attendance_qr_key'), 'notes' => 'Trabajo mediante QR'], $this->headers())->assertOk();
        $this->assertDatabaseHas('activities', ['description' => 'Trabajo mediante QR']);
        $activity = Activity::firstOrFail();
        $this->assertSame('qr', $activity->metadata['method']);
        $this->travelTo(now()->addDay());
        $service = app(AttendanceService::class);
        $service->checkIn($user, location: $this->attendancePosition(), method: 'web');
        $service->checkOut($user, location: $this->attendancePosition(), method: 'web', notes: 'Trabajo mediante web');
        $this->assertDatabaseCount('activities', 2);
        $this->assertSame('gps', Activity::where('description', 'Trabajo mediante web')->firstOrFail()->metadata['method']);
    }

    public function test_optional_empty_exit_does_not_create_a_fake_activity(): void
    {
        $this->employee(false);
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertCreated();
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition(), 'notes' => '   '], $this->headers())->assertOk();
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_corrections_update_the_same_activity_and_direct_edits_are_rejected(): void
    {
        $user = $this->employee();
        $service = app(AttendanceService::class);
        $service->checkIn($user, location: $this->attendancePosition());
        $attendance = $service->checkOut($user, location: $this->attendancePosition(), notes: 'Antes');
        $activityId = Activity::firstOrFail()->id;
        $admin = $this->createPrincipalAdmin(['role' => 'admin', 'api_token' => 'admin']);
        $headers = ['Authorization' => 'Bearer admin'];
        $this->patchJson('/api/v1/attendances/'.$attendance->id, ['daily_activity' => 'Corregida'], $headers)->assertOk();
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseHas('activities', ['id' => $activityId, 'description' => 'Corregida']);
        $this->patchJson('/api/v1/activities/'.$activityId, ['description' => 'Texto independiente'], $headers)->assertUnprocessable();
        $this->deleteJson('/api/v1/activities/'.$activityId, [], $headers)->assertUnprocessable();
        $this->deleteJson('/api/v1/attendances/'.$attendance->id, [], $headers)->assertOk();
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_deleting_an_attendance_with_activity_hours_rolls_back(): void
    {
        $user = $this->employee(false);
        $service = app(AttendanceService::class);
        $service->checkIn($user, location: $this->attendancePosition());
        $attendance = $service->checkOut($user, location: $this->attendancePosition(), notes: 'Inventario');
        $activity = Activity::firstOrFail();
        Hour::factory()->create(['activity_id' => $activity->id, 'user_id' => $user->id]);
        $this->createPrincipalAdmin(['role' => 'admin', 'api_token' => 'admin']);
        $headers = ['Authorization' => 'Bearer admin'];
        $this->deleteJson('/api/v1/attendances/'.$attendance->id, [], $headers)->assertUnprocessable();
        $this->patchJson('/api/v1/attendances/'.$attendance->id, ['daily_activity' => null], $headers)->assertUnprocessable();
        $this->assertSame('Inventario', $attendance->fresh()->daily_activity);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('activities', 1);
    }

    public function test_activity_listing_filters_dates_and_never_exposes_other_users(): void
    {
        $user = $this->employee(false);
        Activity::factory()->create(['user_id' => $user->id, 'type' => 'work', 'occurred_at' => now()]);
        Activity::factory()->create(['user_id' => $user->id, 'type' => 'login', 'occurred_at' => now()]);
        Activity::factory()->create(['user_id' => $user->id, 'type' => 'work', 'occurred_at' => now()->subWeek()]);
        Activity::factory()->create(['type' => 'work', 'occurred_at' => now()]);
        $this->getJson('/api/v1/activities?type=work&date_from='.now()->toDateString().'&per_page=20', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $user->id);
        $this->getJson('/api/v1/activities?date_from=incorrecta', $this->headers())->assertUnprocessable();
    }

    public function test_explicit_qr_action_cannot_flip_a_retried_entry_into_exit(): void
    {
        $this->employee(false);
        $payload = ['qr_data' => config('app.attendance_qr_key'), 'action' => 'check_in'];
        $this->postJson('/api/v1/attendance/scan', $payload, $this->headers())->assertCreated();
        $this->postJson('/api/v1/attendance/scan', $payload, $this->headers())->assertUnprocessable();
        $this->assertNull(Attendance::firstOrFail()->check_out_at);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_manual_activity_cannot_forge_login_or_security_context(): void
    {
        $user = $this->createPrincipalAdmin(['role' => 'admin', 'api_token' => 'admin']);
        $headers = ['Authorization' => 'Bearer admin'];
        $payload = ['user_id' => $user->id, 'type' => 'login', 'name' => 'Falso inicio', 'occurred_at' => now()->toIso8601String()];
        $this->postJson('/api/v1/activities', $payload, $headers)->assertUnprocessable();
        $payload['type'] = 'work';
        $payload['ip_address'] = '1.2.3.4';
        $payload['metadata'] = ['source' => 'attendance'];
        $payload['attendance_id'] = 999;
        $response = $this->postJson('/api/v1/activities', $payload, $headers)->assertCreated();
        $this->assertNotSame('1.2.3.4', $response->json('ip_address'));
        $this->assertNull($response->json('metadata'));
        $this->assertNull($response->json('attendance_id'));
    }

    public function test_web_activity_form_supplies_owner_type_and_date(): void
    {
        $user = $this->createPrincipalAdmin(['role' => 'admin']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(CreateActivity::class)
            ->fillForm(['user_id' => $user->id, 'name' => 'Actividad desde web', 'description' => str_repeat('Trabajo ', 100), 'occurred_at' => now()->format('Y-m-d H:i:s')])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('activities', ['user_id' => $user->id, 'name' => 'Actividad desde web', 'type' => 'work']);
    }

    public function test_migration_integrates_existing_long_activity_descriptions(): void
    {
        $user = $this->employee(false);
        $service = app(AttendanceService::class);
        $service->checkIn($user, location: $this->attendancePosition());
        $attendance = $service->checkOut($user, location: $this->attendancePosition(), notes: str_repeat('Actividad histórica ', 100));
        Activity::query()->delete();
        $migration = require database_path('migrations/2026_10_10_203119_link_activities_to_attendances.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('activities', 1);
        $this->assertSame(trim($attendance->daily_activity), Activity::firstOrFail()->description);
        $this->assertSame($attendance->id, Activity::firstOrFail()->attendance_id);
        $attendance->update(['daily_activity' => 'Texto actualizado']);
        $this->assertDatabaseCount('activities', 1);
        $this->assertSame('Texto actualizado', Activity::firstOrFail()->description);
    }
}
