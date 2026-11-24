<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Departament;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DepartamentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Departament');
    }

    public function view(AuthUser $authUser, Departament $departament): bool
    {
        return $authUser->isSuperAdmin()
            || ($authUser->can('View:Departament')
                && ((int) $authUser->department_id === (int) $departament->id
                    || $authUser->canManageDepartmentId((int) $departament->id)));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->isSuperAdmin();
    }

    public function update(AuthUser $authUser, Departament $departament): bool
    {
        return $authUser->isSuperAdmin();
    }

    public function delete(AuthUser $authUser, Departament $departament): bool
    {
        return $authUser->isSuperAdmin();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Departament $departament): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Departament $departament): bool
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

    public function replicate(AuthUser $authUser, Departament $departament): bool
    {
        return $authUser->can('Replicate:Departament');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Departament');
    }
}
