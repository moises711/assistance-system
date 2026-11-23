<?php

namespace Tests\Feature;

use App\Filament\Resources\WorkSites\Pages\ManageWorkSites;
use App\Models\Attendance;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendanceGpsTest extends TestCase
{
    use RefreshDatabase;

    private function employee(bool $required = false): User
    {
        return $this->assignAttendanceSite(User::factory()->create(['api_token' => 'gps-employee', 'require_activity_report' => $required]));
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer gps-employee'];
    }

    private function enter(): void
    {
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertCreated();
    }

    public function test_manual_entry_and_exit_validate_gps_and_store_server_evidence(): void
    {
        $user = $this->employee(true);
        $this->getJson('/api/v1/me', $this->headers())->assertOk()->assertJsonPath('gps_validation_version', 1)->assertJsonPath('attendance_location.site.id', $user->work_site_id);
        $this->enter();
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition(), 'notes' => 'Inventario'], $this->headers())
            ->assertOk()->assertJsonPath('attendance.check_out_method', 'gps')
            ->assertJsonPath('attendance.check_out_location.work_site_id', $user->work_site_id);
        $record = Attendance::firstOrFail();
        $this->assertSame('gps', $record->check_in_method);
        $this->assertEquals(0, $record->check_in_location['distance_meters']);
        $this->assertDatabaseHas('activities', ['attendance_id' => $record->id, 'description' => 'Inventario']);
    }

    public function test_both_manual_marks_reject_missing_location(): void
    {
        $this->employee();
        $this->postJson('/api/v1/attendance/check-in', [], $this->headers())->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->assertDatabaseCount('attendances', 0);
        $this->enter();
        $this->postJson('/api/v1/attendance/check-out', [], $this->headers())->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->assertNull(Attendance::firstOrFail()->check_out_at);
    }

    public function test_outside_positions_cannot_mark_entry_or_exit(): void
    {
        $this->employee();
        $outside = [...$this->attendancePosition(), 'latitude' => -12.06, 'work_site_id' => 999, 'distance_meters' => 0];
        $this->postJson('/api/v1/attendance/check-in', ['location' => $outside], $this->headers())->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 0);
        $this->enter();
        $this->postJson('/api/v1/attendance/check-out', ['location' => $outside], $this->headers())->assertUnprocessable();
        $this->assertNull(Attendance::firstOrFail()->check_out_at);
    }

    public function test_stale_future_imprecise_mocked_and_invalid_positions_are_rejected(): void
    {
        $this->employee();
        foreach ([['captured_at' => now()->subSeconds(61)->toIso8601String()], ['captured_at' => now()->addMinute()->toIso8601String()],
            ['accuracy' => 100], ['accuracy' => 0], ['latitude' => 91], ['longitude' => -181], ['is_mocked' => true]] as $invalid) {
            $this->postJson('/api/v1/attendance/check-in', ['location' => [...$this->attendancePosition(), ...$invalid]], $this->headers())->assertUnprocessable();
        }
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_unassigned_or_inactive_site_blocks_manual_marks_even_for_remote_users(): void
    {
        $user = $this->employee();
        $site = $user->workSite;
        $user->update(['work_site_id' => null, 'allows_remote' => true]);
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertUnprocessable();
        $user->update(['work_site_id' => $site->id]);
        $site->update(['is_active' => false]);
        $this->postJson('/api/v1/attendance/check-in', ['location' => $this->attendancePosition()], $this->headers())->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_qr_never_requires_gps_but_respects_the_same_activity_requirement(): void
    {
        User::factory()->create(['api_token' => 'gps-employee', 'require_activity_report' => true]);
        $qr = ['qr_data' => config('app.attendance_qr_key')];
        $this->postJson('/api/v1/attendance/scan', [...$qr, 'action' => 'check_in'], $this->headers())->assertCreated();
        $this->postJson('/api/v1/attendance/scan', [...$qr, 'action' => 'check_out'], $this->headers())->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->postJson('/api/v1/attendance/scan', [...$qr, 'action' => 'check_out', 'notes' => 'Salida QR'], $this->headers())->assertOk();
        $record = Attendance::firstOrFail();
        $this->assertNull($record->check_in_location);
        $this->assertNull($record->check_out_location);
        $this->assertSame('qr', $record->check_out_method);
    }

    public function test_manual_and_qr_exit_share_the_administrator_activity_switch(): void
    {
        $user = $this->employee(true);
        $this->enter();
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition()], $this->headers())->assertUnprocessable()->assertJsonValidationErrors('notes');
        $user->update(['require_activity_report' => false]);
        $this->postJson('/api/v1/attendance/check-out', ['location' => $this->attendancePosition()], $this->headers())->assertOk();
    }

    public function test_only_authorized_administrators_can_capture_and_edit_work_sites(): void
    {
        $this->employee();
        $data = ['name' => 'Oficina', 'radius_meters' => 100, 'location' => $this->attendancePosition()];
        $this->postJson('/api/v1/work-sites', $data, $this->headers())->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'api_token' => 'admin']);
        $headers = ['Authorization' => 'Bearer admin'];
        $response = $this->postJson('/api/v1/work-sites', $data, $headers)->assertCreated()->assertJsonPath('captured_by', $admin->id);
        $site = $response->json('id');
        $this->getJson('/api/v1/work-sites', $headers)->assertOk();
        $this->patchJson('/api/v1/work-sites/'.$site, ['name' => 'Oficina', 'radius_meters' => 50, 'is_active' => false], $headers)->assertOk()->assertJsonPath('radius_meters', 50);
        $this->postJson('/api/v1/work-sites', ['name' => 'Sin GPS', 'radius_meters' => 100], $headers)->assertUnprocessable();
    }

    public function test_ordinary_crud_permissions_cannot_bypass_self_service_gps(): void
    {
        $user = $this->employee();
        $user->givePermissionTo(Permission::findOrCreate('Create:Attendance', 'web'));
        $this->postJson('/api/v1/attendances', ['user_id' => $user->id, 'date' => now()->toDateString(), 'status' => 'presente', 'check_in_at' => now()->toIso8601String()], $this->headers())->assertForbidden();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_web_site_capture_saves_the_administrator_position(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageWorkSites::class)
            ->callAction('create', data: ['name' => 'Sede web', 'radius_meters' => 100, 'is_active' => true, 'location' => $this->attendancePosition()])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('work_sites', ['name' => 'Sede web', 'captured_by' => $admin->id, 'latitude' => -12.0464]);
    }

    public function test_accuracy_margin_is_checked_near_the_area_boundary(): void
    {
        $this->employee();
        $nearBoundary = [...$this->attendancePosition(), 'latitude' => -12.0464 + 80 / 111194.9, 'accuracy' => 25];
        $this->postJson('/api/v1/attendance/check-in', ['location' => $nearBoundary], $this->headers())->assertUnprocessable();
        $nearBoundary['accuracy'] = 5;
        $this->postJson('/api/v1/attendance/check-in', ['location' => $nearBoundary], $this->headers())->assertCreated();
    }
}
