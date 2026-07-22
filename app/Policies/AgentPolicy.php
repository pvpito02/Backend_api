<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin']);
    }

    public function view(User $user, Agent $agent): bool
    {
        if ($user->hasRole(['super_admin', 'admin', 'sous_admin'])) {
            return true;
        }

        // Agent : uniquement sa propre fiche
        return $user->hasRole('agent') && $user->agent?->id === $agent->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, Agent $agent): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
