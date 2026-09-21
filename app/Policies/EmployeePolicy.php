<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class EmployeePolicy
{
    public function viewOwnProfile(User $authUser, User $user): bool
    {
        return $authUser->id === $user->id;
    }

    public function viewOwnAttendance(User $authUser, Attendance $attendance): bool
    {
        return $attendance->user_id === $authUser->id;
    }

    public function createAttendance(User $authUser): bool
    {
        return $authUser->isEmployee() || $authUser->isIntern();
    }

    public function updateOwnAttendance(User $authUser, Attendance $attendance): bool
    {
        return $attendance->user_id === $authUser->id
            && ($authUser->isEmployee() || $authUser->isIntern());
    }

    public function viewOwnSchedule(User $authUser, User $user): bool
    {
        return $authUser->id === $user->id;
    }

    public function manageOwnAccount(User $authUser, User $user): bool
    {
        return $authUser->id === $user->id;
    }
}
