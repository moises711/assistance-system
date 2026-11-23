<?php

namespace Tests;

use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
