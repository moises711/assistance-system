<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(User $authUser, User $user): bool
    {
        return $authUser->canManageUser($user)
            && ((int) $authUser->id === (int) $user->id || $authUser->can('View:User'));
    }

    public function create(User $authUser): bool
    {
        return $authUser->isManager()
            && $authUser->can('Create:User')
            && ($authUser->isSuperAdmin() || $authUser->managedDepartmentIds() !== []);
    }

    public function update(User $authUser, User $user): bool
    {
        return $authUser->can('Update:User')
            && $authUser->canManageUser($user)
            && $this->canManageUserPrivileges($authUser, $user);
    }

    public function delete(User $authUser, User $user): bool
    {
        return false;
    }

    public function deleteAny(User $authUser): bool
    {
        return false;
    }

    public function restore(User $authUser, User $user): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return false;
    }

    public function restoreAny(User $authUser): bool
    {
        return false;
    }

    public function replicate(User $authUser, User $user): bool
    {
        return $authUser->isManager() && $authUser->can('Replicate:User')
            && $authUser->canManageUser($user);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('Reorder:User');
    }

    private function canManageUserPrivileges(User $authUser, User $user): bool
    {
        $superAdminRole = config('filament-shield.super_admin.name', 'super_admin');

        if ($user->hasRole($superAdminRole)) {
            return $authUser->hasRole($superAdminRole);
        }

        if ($authUser->hasRole($superAdminRole)) {
            return true;
        }

        $actorPermissions = $authUser->getAllPermissions()->pluck('name');
        $targetPermissions = $user->getAllPermissions()->pluck('name');

        return $targetPermissions->diff($actorPermissions)->isEmpty();
    }
}
