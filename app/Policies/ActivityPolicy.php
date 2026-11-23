<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Activity;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Activity');
    }

    public function view(AuthUser $authUser, Activity $activity): bool
    {
        return (int) $activity->user_id === (int) $authUser->id
            || ($authUser->isManager() && $authUser->can('View:Activity'));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Activity');
    }

    public function update(AuthUser $authUser, Activity $activity): bool
    {
        return $activity->attendance_id === null && ! $activity->isLogin() && $authUser->can('Update:Activity')
            && ((int) $activity->user_id === (int) $authUser->id || $authUser->isManager());
    }

    public function delete(AuthUser $authUser, Activity $activity): bool
    {
        return $activity->attendance_id === null && ! $activity->isLogin() && $authUser->can('Delete:Activity')
            && ((int) $activity->user_id === (int) $authUser->id || $authUser->isManager());
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Activity');
    }

    public function restore(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->can('Restore:Activity');
    }

    public function forceDelete(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->can('ForceDelete:Activity');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Activity');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Activity');
    }

    public function replicate(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->can('Replicate:Activity');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Activity');
    }
}
