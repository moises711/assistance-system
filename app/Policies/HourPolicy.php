<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Hour;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class HourPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Hour');
    }

    public function view(AuthUser $authUser, Hour $hour): bool
    {
        return $authUser->canManageUserId((int) $hour->user_id)
            && ((int) $hour->user_id === (int) $authUser->id || $authUser->can('View:Hour'));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Hour');
    }

    public function update(AuthUser $authUser, Hour $hour): bool
    {
        return $authUser->can('Update:Hour')
            && $authUser->canManageUserId((int) $hour->user_id);
    }

    public function delete(AuthUser $authUser, Hour $hour): bool
    {
        return $authUser->can('Delete:Hour')
            && $authUser->canManageUserId((int) $hour->user_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('DeleteAny:Hour');
    }

    public function restore(AuthUser $authUser, Hour $hour): bool
    {
        return $authUser->isManager() && $authUser->can('Restore:Hour');
    }

    public function forceDelete(AuthUser $authUser, Hour $hour): bool
    {
        return $authUser->isManager() && $authUser->can('ForceDelete:Hour');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('ForceDeleteAny:Hour');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('RestoreAny:Hour');
    }

    public function replicate(AuthUser $authUser, Hour $hour): bool
    {
        return $authUser->can('Replicate:Hour');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Hour');
    }
}
