<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use WallaceMartinss\FilamentSecurity\EventLog\Models\SecurityEvent;

class SecurityEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SecurityEvent');
    }

    public function view(AuthUser $authUser, SecurityEvent $securityEvent): bool
    {
        return $authUser->can('View:SecurityEvent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SecurityEvent');
    }

    public function update(AuthUser $authUser, SecurityEvent $securityEvent): bool
    {
        return $authUser->can('Update:SecurityEvent');
    }

    public function delete(AuthUser $authUser, SecurityEvent $securityEvent): bool
    {
        return $authUser->can('Delete:SecurityEvent');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('DeleteAny:SecurityEvent');
    }

    public function restore(AuthUser $authUser, SecurityEvent $securityEvent): bool
    {
        return $authUser->isManager() && $authUser->can('Restore:SecurityEvent');
    }

    public function forceDelete(AuthUser $authUser, SecurityEvent $securityEvent): bool
    {
        return $authUser->isManager() && $authUser->can('ForceDelete:SecurityEvent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('ForceDeleteAny:SecurityEvent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->isManager() && $authUser->can('RestoreAny:SecurityEvent');
    }

    public function replicate(AuthUser $authUser, SecurityEvent $securityEvent): bool
    {
        return $authUser->can('Replicate:SecurityEvent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SecurityEvent');
    }
}
