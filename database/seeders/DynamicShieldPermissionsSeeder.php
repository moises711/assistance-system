<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class DynamicShieldPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $exitCode = Artisan::call('shield:generate', [
            '--all' => true,
            '--option' => 'permissions',
            '--panel' => 'admin',
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('No se pudieron generar los permisos de Filament Shield: '.Artisan::output());
        }

        $guard = config('auth.defaults.guard', 'web');
        $permissionModel = Utils::getPermissionModel();
        $roleModel = Utils::getRoleModel();

        $employeePermissions = [
            'View:Dashboard',
            'View:DashboardEmpleado',
            'View:EmployeeAttendanceStats',
            'View:RoleDashboardStats',
            'ViewAny:Attendance',
            'View:Attendance',
            'Create:Attendance',
            'Update:Attendance',
            'ViewAny:Activity',
            'View:Activity',
            'Create:Activity',
        ];

        $managerPermissions = array_values(array_unique([
            ...$employeePermissions,
            'Manage:AllRecords',
            'AssignRoles:User',
            'AssignWorkSite:User',
            'Manage:AttendanceSettings:User',
            'Deactivate:User',
            'SendInvitation:User',
            'ViewAny:User', 'View:User', 'Create:User', 'Update:User',
            'ViewAny:Hour', 'View:Hour', 'Create:Hour', 'Update:Hour',
            'ViewAny:Departament', 'View:Departament',
            'ViewAny:Report', 'View:Report', 'Create:Report', 'Update:Report',
            'ViewAny:Calendar', 'View:Calendar', 'Create:Calendar', 'Update:Calendar',
            'View:SupervisorDashboard', 'View:MainDashboardStats', 'View:RrhhStats', 'View:FullCalendarWidget',
        ]));

        $permissionsToEnsure = array_unique([
            ...array_keys(config('filament-shield.custom_permissions', [])),
            ...$employeePermissions,
            ...$managerPermissions,
        ]);

        foreach ($permissionsToEnsure as $permission) {
            $permissionModel::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $roleModel::firstOrCreate([
            'name' => config('filament-shield.super_admin.name', 'super_admin'),
            'guard_name' => $guard,
        ]);

        $employeeRole = $roleModel::firstOrCreate([
            'name' => 'empleados',
            'guard_name' => $guard,
        ]);
        $employeeRole->syncPermissions($employeePermissions);

        $managerRole = $roleModel::firstOrCreate([
            'name' => 'gerente',
            'guard_name' => $guard,
        ]);
        $managerRole->syncPermissions($managerPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
