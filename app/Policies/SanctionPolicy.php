<?php

namespace App\Policies;

use App\Models\Sanction;
use App\Models\User;

class SanctionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin']);
    }

    public function view(User $user, Sanction $sanction): bool
    {
        if ($user->hasRole(['super_admin', 'admin', 'sous_admin'])) {
            return true;
        }

        return $user->hasRole('agent') && $user->agent?->id === $sanction->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, Sanction $sanction): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Sanction $sanction): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
