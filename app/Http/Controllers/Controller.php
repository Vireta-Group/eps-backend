<?php

namespace App\Http\Controllers;

use App\Models\User;

abstract class Controller
{
    /**
     * Determine whether the user holds any of the given role slugs.
     *
     * @param  array<int, string>  $slugs
     */
    protected function userHasAnyRole(User $user, array $slugs): bool
    {
        return $user->roles()->whereIn('slug', $slugs)->exists();
    }

    /**
     * Determine whether the user may manage (mark/edit) attendance records.
     */
    protected function canManageAttendance(User $user): bool
    {
        return $this->userHasAnyRole($user, [
            'school_admin',
            'super_admin',
            'principal',
            'vice_principal',
            'hr_manager',
        ]);
    }
}
