<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Departament;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. IMPORTANTE: Generar los roles y permisos iniciales de Shield
        $this->call([
            ShieldSeeder::class, // <-- Seeder generado por Filament Shield
        ]);

        // Asegurar la existencia de los roles de Spatie
        $adminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $employeeRole = Role::firstOrCreate(['name' => 'Empleados']);

        // 2. Crear 4 Departamentos
        $departments = Departament::factory()->count(4)->create();

        // 3. Crear Administrador y vincularlo con Spatie y Shield
        $admin = User::factory()->create([
            'name' => 'santi',
            'dni' => '345678',
            'email' => 'santi@gmail.com',
            'password' => bcrypt('12345678'),
            'role' => UserRole::Admin->value,
            'department_id' => $departments->first()->id,
        ]);
        $admin->assignRole($adminRole); // <-- Asigna el rol en model_has_roles

        // 4. Crear Empleado de prueba y vincularlo con Spatie y Shield
        $empleado = User::factory()->create([
            'name' => 'empleado',
            'dni' => '87654321',
            'email' => 'moises@gmail.com',
            'password' => bcrypt('12345678'),
            'role' => UserRole::Employee->value,
            'department_id' => $departments->first()->id,
        ]);
        $empleado->assignRole($employeeRole); // <-- Asigna el rol en model_has_roles

        // 5. Crear 10 usuarios adicionales
        $users = User::factory()
            ->count(10)
            ->recycle($departments)
            ->create([
                'role' => UserRole::Employee->value,
            ]);

        foreach ($users as $user) {
            $user->assignRole($employeeRole); // <-- Asigna a todos los empleados
        }

        // Historial de Asistencias
        $allUsers = $users->push($admin)->push($empleado);

        foreach ($allUsers as $user) {
            for ($i = 10; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);

                if ($date->isWeekend()) {
                    continue;
                }

                Attendance::factory()->create([
                    'user_id' => $user->id,
                    'department_id' => $user->department_id,
                    'date' => $date->toDateString(),
                ]);
            }
        }
    }
}