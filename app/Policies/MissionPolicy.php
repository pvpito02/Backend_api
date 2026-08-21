<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, Mission $mission): bool
    {
        if ($user->isAdminStaff()) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $mission->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, Mission $mission): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Mission $mission): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
