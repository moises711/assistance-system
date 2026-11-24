<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        return $this->isSuperAdmin($authUser)
            && $role->name !== config('filament-shield.super_admin.name', 'super_admin');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Role $role): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Role $role): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, Role $role): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }

    private function isSuperAdmin(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'hasRole')
            && $authUser->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }
}
