<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('calendrier.manage')
            || $user->hasRole(['sous_admin', 'conseiller', 'agent']);
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->staffCan('calendrier.manage');
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->staffCan('calendrier.manage');
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->staffCan('calendrier.manage');
    }
}
