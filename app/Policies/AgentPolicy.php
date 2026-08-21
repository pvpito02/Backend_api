<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('agents.manage') || $user->hasRole('sous_admin');
    }

    public function view(User $user, Agent $agent): bool
    {
        if ($user->staffCan('agents.manage') || $user->hasRole('sous_admin')) {
            return true;
        }

        // Terrain : uniquement sa propre fiche
        return $user->isFieldUser() && $user->agent?->id === $agent->id;
    }

    public function create(User $user): bool
    {
        return $user->staffCan('agents.manage');
    }

    public function update(User $user, Agent $agent): bool
    {
        return $user->staffCan('agents.manage');
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $user->staffCan('agents.manage');
    }
}
