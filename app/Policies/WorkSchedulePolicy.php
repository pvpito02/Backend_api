<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSchedule;

class WorkSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('planning.manage')
            || $user->hasRole(['sous_admin', 'conseiller', 'agent']);
    }

    public function view(User $user, WorkSchedule $workSchedule): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->staffCan('planning.manage');
    }

    public function create(User $user): bool
    {
        return $user->staffCan('planning.manage');
    }
}
