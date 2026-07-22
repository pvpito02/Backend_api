<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
