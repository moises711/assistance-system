<?php

namespace Tests;

use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function createPrincipalAdmin(array $attributes = []): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $user;
    }

    protected function attendancePosition(): array
    {
        return ['latitude' => -12.0464, 'longitude' => -77.0428, 'accuracy' => 5, 'captured_at' => now()->toIso8601String(), 'is_mocked' => false];
    }

    protected function assignAttendanceSite(User $user): User
    {
        $user->update(['work_site_id' => WorkSite::factory()->create()->id]);

        return $user;
    }
}
