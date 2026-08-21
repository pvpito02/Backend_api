<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('missions.manage')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, Mission $mission): bool
    {
        if ($user->staffCan('missions.manage') || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $mission->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->staffCan('missions.manage');
    }

    public function update(User $user, Mission $mission): bool
    {
        return $user->staffCan('missions.manage');
    }

    public function delete(User $user, Mission $mission): bool
    {
        return $user->staffCan('missions.manage');
    }
}
