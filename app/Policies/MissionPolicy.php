<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, Mission $mission): bool
    {
        if ($user->hasRole(['super_admin', 'admin', 'sous_admin'])) {
            return true;
        }

        return $user->hasRole('agent') && $user->agent?->id === $mission->agent_id;
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
