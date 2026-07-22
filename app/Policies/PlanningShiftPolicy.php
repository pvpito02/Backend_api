<?php

namespace App\Policies;

use App\Models\PlanningShift;
use App\Models\User;

class PlanningShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, PlanningShift $planningShift): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, PlanningShift $planningShift): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, PlanningShift $planningShift): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
