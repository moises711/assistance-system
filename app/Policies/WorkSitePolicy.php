<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSite;

class WorkSitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:WorkSite');
    }

    public function view(User $user, WorkSite $site): bool
    {
        return $user->can('View:WorkSite');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:WorkSite');
    }

    public function update(User $user, WorkSite $site): bool
    {
        return $user->can('Update:WorkSite');
    }

    public function delete(User $user, WorkSite $site): bool
    {
        return $user->can('Delete:WorkSite');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
