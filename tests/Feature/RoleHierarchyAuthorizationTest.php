<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Departament;
use App\Models\Report;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleHierarchyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_only_the_principal_administrator_can_manage_roles(): void
    {
        $secondaryAdminRole = Role::create(['name' => 'administrador_secundario', 'guard_name' => 'web']);
        $secondaryAdminRole->givePermissionTo($this->permission('ViewAny:Role'));
        $secondaryAdmin = User::factory()->create();
        $secondaryAdmin->assignRole($secondaryAdminRole);

        $principalAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $principalAdmin = User::factory()->create();
        $principalAdmin->assignRole($principalAdminRole);

        $this->assertFalse($secondaryAdmin->can('viewAny', Role::class));
        $this->assertFalse($secondaryAdmin->can('create', Role::class));
        $this->assertTrue($principalAdmin->can('viewAny', Role::class));
        $this->assertTrue($principalAdmin->can('create', Role::class));

        $this->actingAs($principalAdmin)->get('/admin/shield/roles/create')->assertOk();
        $this->actingAs($secondaryAdmin)->get('/admin/shield/roles/create')->assertForbidden();
    }

    public function test_manager_can_manage_only_users_whose_permissions_fit_within_their_own(): void
    {
        $managerRole = Role::create(['name' => 'gerente_sucursal', 'guard_name' => 'web']);
        $managerRole->givePermissionTo([
            $this->permission('Manage:AllRecords'),
            $this->permission('Update:User'),
            $this->permission('ViewAny:User'),
            $this->permission('View:DashboardEmpleado'),
            $this->permission('View:Attendance'),
            $this->permission('Update:Attendance'),
            $this->permission('Correct:Attendance'),
        ]);
        $manager = User::factory()->create();
        $manager->assignRole($managerRole);

        $employeeRole = Role::create(['name' => 'empleado_operaciones', 'guard_name' => 'web']);
        $employeeRole->givePermissionTo($this->permission('View:DashboardEmpleado'));
        $employee = User::factory()->create(['department_id' => $manager->department_id]);
        $employee->assignRole($employeeRole);
        $outsideDepartment = User::factory()->create();

        $privilegedRole = Role::create(['name' => 'administrador_finanzas', 'guard_name' => 'web']);
        $privilegedRole->givePermissionTo($this->permission('Manage:UserSecurity'));
        $privilegedUser = User::factory()->create(['department_id' => $manager->department_id]);
        $privilegedUser->assignRole($privilegedRole);

        $this->assertTrue($manager->can('update', $employee));
        $this->assertFalse($manager->can('update', $privilegedUser));
        $this->assertFalse($manager->can('update', $outsideDepartment));
        $this->assertTrue($manager->can('viewAny', User::class));
        $this->assertFalse($manager->can('deleteAny', User::class));

        $this->assertTrue($manager->applyTeamScopeTo(User::query()->whereKey($employee->id), 'users.id')->exists());
        $this->assertFalse($manager->applyTeamScopeTo(User::query()->whereKey($outsideDepartment->id), 'users.id')->exists());

        $teamAttendance = new Attendance(['user_id' => $employee->id]);
        $outsideAttendance = new Attendance(['user_id' => $outsideDepartment->id]);
        $this->assertTrue($manager->can('view', $teamAttendance));
        $this->assertFalse($manager->can('view', $outsideAttendance));
    }

    public function test_employee_can_act_on_own_attendance_but_not_another_employee_or_bulk_records(): void
    {
        $employeeRole = Role::create(['name' => 'tecnico_campo', 'guard_name' => 'web']);
        $employeeRole->givePermissionTo([
            $this->permission('ViewAny:Attendance'),
            $this->permission('View:Attendance'),
            $this->permission('Update:Attendance'),
            $this->permission('Correct:Attendance'),
            $this->permission('DeleteAny:Attendance'),
            $this->permission('ForceDeleteAny:Attendance'),
        ]);
        $employee = User::factory()->create();
        $employee->assignRole($employeeRole);
        $coworker = User::factory()->create();

        $ownAttendance = new Attendance(['user_id' => $employee->id]);
        $coworkerAttendance = new Attendance(['user_id' => $coworker->id]);

        $this->assertTrue($employee->can('view', $ownAttendance));
        $this->assertFalse($employee->can('view', $coworkerAttendance));
        $this->assertTrue($employee->can('update', $ownAttendance));
        $this->assertFalse($employee->can('update', $coworkerAttendance));
        $this->assertFalse($employee->can('deleteAny', Attendance::class));
        $this->assertFalse($employee->can('forceDeleteAny', Attendance::class));
    }

    public function test_manager_api_lists_only_their_department_and_rejects_cross_department_assignment(): void
    {
        $managerRole = Role::create(['name' => 'gerente_operaciones_api', 'guard_name' => 'web']);
        $managerRole->givePermissionTo([
            $this->permission('Manage:AllRecords'),
            $this->permission('ViewAny:Attendance'),
            $this->permission('Create:Activity'),
        ]);
        $manager = User::factory()->create(['api_token' => 'department-manager']);
        $manager->assignRole($managerRole);
        $teamMember = User::factory()->create(['department_id' => $manager->department_id]);
        $outsideMember = User::factory()->create(['api_token' => 'outside-member']);
        $teamAttendance = Attendance::factory()->create([
            'user_id' => $teamMember->id,
            'department_id' => $manager->department_id,
        ]);
        Attendance::factory()->create(['user_id' => $outsideMember->id]);

        $headers = ['Authorization' => 'Bearer department-manager'];
        $this->getJson('/api/v1/attendances?scope=all', $headers)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $teamAttendance->id);

        $this->postJson('/api/v1/activities', [
            'user_id' => $outsideMember->id,
            'type' => 'work',
            'name' => 'Intento fuera del equipo',
            'occurred_at' => now()->toIso8601String(),
        ], $headers)->assertForbidden();
    }

    public function test_one_manager_can_be_assigned_multiple_departments_without_getting_global_scope(): void
    {
        $managerRole = Role::create(['name' => 'gerente_multiple_departamentos', 'guard_name' => 'web']);
        $managerRole->givePermissionTo($this->permission('Manage:AllRecords'));
        $manager = User::factory()->create();
        $manager->assignRole($managerRole);

        $firstDepartment = Departament::factory()->create();
        $secondDepartment = Departament::factory()->create();
        $outsideDepartment = Departament::factory()->create();
        $manager->departmentsManaged()->attach([$firstDepartment->id, $secondDepartment->id]);

        $firstEmployee = User::factory()->create(['department_id' => $firstDepartment->id]);
        $secondEmployee = User::factory()->create(['department_id' => $secondDepartment->id]);
        $outsideEmployee = User::factory()->create(['department_id' => $outsideDepartment->id]);

        $this->assertEqualsCanonicalizing(
            [$firstDepartment->id, $secondDepartment->id],
            $manager->managedDepartmentIds(),
        );
        $this->assertTrue($manager->canManageUser($firstEmployee));
        $this->assertTrue($manager->canManageUser($secondEmployee));
        $this->assertFalse($manager->canManageUser($outsideEmployee));
        $this->assertCount(2, $manager->applyTeamScopeTo(User::query(), 'users.id')->get());
    }

    public function test_report_manager_filter_matches_staff_under_that_manager(): void
    {
        $manager = User::factory()->create();
        $department = Departament::factory()->create();
        $manager->departmentsManaged()->attach($department);
        $employee = User::factory()->create(['department_id' => $department->id]);
        $report = Report::factory()->create(['user_id' => $employee->id]);

        $query = Report::query()->whereHas(
            'user.department.managers',
            fn ($managerQuery) => $managerQuery->whereKey($manager->id),
        );

        $this->assertTrue($query->whereKey($report->id)->exists());
    }

    public function test_attendance_changes_record_the_actor_and_changed_values(): void
    {
        $actor = User::factory()->create();
        $owner = User::factory()->create();
        $attendance = Attendance::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($actor);

        $attendance->update(['daily_activity' => 'Actividad corregida']);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->id,
            'subject_user_id' => $owner->id,
            'action' => 'attendance.updated',
            'auditable_type' => Attendance::class,
            'auditable_id' => $attendance->id,
        ]);

        $log = AuditLog::query()->where('action', 'attendance.updated')->latest('id')->firstOrFail();
        $this->assertSame('Actividad corregida', $log->new_values['daily_activity']);
    }

    public function test_deactivated_users_cannot_sign_in_or_access_the_panel_without_deleting_history(): void
    {
        $user = User::factory()->create(['is_active' => false, 'api_token' => 'inactive-token']);
        $attendance = Attendance::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])->assertForbidden();
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'user_id' => $user->id]);
    }

    public function test_password_reset_link_is_sent_and_filament_reset_page_is_available(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->assertSame(Password::RESET_LINK_SENT, Password::sendResetLink(['email' => $user->email]));
        Notification::assertSentTo($user, ResetPassword::class);
        $this->get('/admin/password-reset/request')->assertOk();
    }

    public function test_audit_log_screen_is_read_only_and_restricted_to_principal_administrator(): void
    {
        $managerRole = Role::create(['name' => 'gerente_auditoria', 'guard_name' => 'web']);
        $managerRole->givePermissionTo([
            $this->permission('Manage:AllRecords'),
            $this->permission('ViewAny:AuditLog'),
        ]);
        $manager = User::factory()->create();
        $manager->assignRole($managerRole);

        $root = User::factory()->create();
        $root->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));

        $this->actingAs($root)->get('/admin/audit-logs')->assertOk();
        $this->actingAs($manager)->get('/admin/audit-logs')->assertForbidden();
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }
}
