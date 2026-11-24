<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Calendar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CalendarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Calendar');
    }

    public function view(AuthUser $authUser, Calendar $calendar): bool
    {
        return $authUser->canManageUserId((int) $calendar->user_id)
            && ((int) $calendar->user_id === (int) $authUser->id || $authUser->can('View:Calendar'));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('Create:Calendar');
    }

    public function update(AuthUser $authUser, Calendar $calendar): bool
    {
        return $authUser->isManager() && $authUser->can('Update:Calendar')
            && $authUser->canManageUserId((int) $calendar->user_id);
    }

    public function delete(AuthUser $authUser, Calendar $calendar): bool
    {
        return $authUser->isManager() && $authUser->can('Delete:Calendar')
            && $authUser->canManageUserId((int) $calendar->user_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('DeleteAny:Calendar');
    }

    public function restore(AuthUser $authUser, Calendar $calendar): bool
    {
        return $authUser->isManager() && $authUser->can('Restore:Calendar');
    }

    public function forceDelete(AuthUser $authUser, Calendar $calendar): bool
    {
        return $authUser->isManager() && $authUser->can('ForceDelete:Calendar');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('ForceDeleteAny:Calendar');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('RestoreAny:Calendar');
    }

    public function replicate(AuthUser $authUser, Calendar $calendar): bool
    {
        return $authUser->can('Replicate:Calendar');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Calendar');
    }
}
