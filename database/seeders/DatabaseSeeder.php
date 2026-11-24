<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ShieldSeeder::class);
        $this->call(DynamicShieldPermissionsSeeder::class);

        $adminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::firstOrNew(['email' => 'army27014@gmail.com']);

        if (! $admin->exists) {
            $admin->forceFill([
                'name' => 'Administrador',
                'last_name' => '',
                'dni' => '00000000',
                'password' => Hash::make('@dream600@M'),
                'email_verified_at' => now(),
                'qr_code' => (string) Str::uuid(),
                'is_active' => true,
                'allows_remote' => false,
                'require_activity_report' => false,
            ]);
        }

        $admin->forceFill(['role' => UserRole::Admin->value])->save();
        $admin->assignRole($adminRole);
    }
}
