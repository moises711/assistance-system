<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attendance;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AttendancePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Attendance');
    }

    public function view(AuthUser $authUser, Attendance $attendance): bool
    {
        return $authUser->canManageUserId((int) $attendance->user_id)
            && ((int) $attendance->user_id === (int) $authUser->id || $authUser->can('View:Attendance'));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Attendance') && $authUser->can('Correct:Attendance');
    }

    public function update(AuthUser $authUser, Attendance $attendance): bool
    {
        return $authUser->can('Update:Attendance') && $authUser->can('Correct:Attendance')
            && $authUser->canManageUserId((int) $attendance->user_id);
    }

    public function delete(AuthUser $authUser, Attendance $attendance): bool
    {
        return $authUser->can('Delete:Attendance') && $authUser->can('Correct:Attendance')
            && $authUser->canManageUserId((int) $attendance->user_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('DeleteAny:Attendance');
    }

    public function restore(AuthUser $authUser, Attendance $attendance): bool
    {
        return $authUser->isManager() && $authUser->can('Restore:Attendance');
    }

    public function forceDelete(AuthUser $authUser, Attendance $attendance): bool
    {
        return $authUser->isManager() && $authUser->can('ForceDelete:Attendance');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('ForceDeleteAny:Attendance');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('RestoreAny:Attendance');
    }

    public function replicate(AuthUser $authUser, Attendance $attendance): bool
    {
        return $authUser->can('Replicate:Attendance');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Attendance');
    }
}
