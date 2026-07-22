<?php

namespace App\Policies;

use App\Models\Pointage;
use App\Models\User;

class PointagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, Pointage $pointage): bool
    {
        if ($user->hasRole(['super_admin', 'admin', 'sous_admin'])) {
            return true;
        }

        return $user->hasRole('agent') && $user->agent?->id === $pointage->agent_id;
    }

    public function create(User $user): bool
    {
        // Scan agent + saisie manuelle admin
        return $user->hasRole(['super_admin', 'admin', 'agent']);
    }

    public function update(User $user, Pointage $pointage): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Pointage $pointage): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function scan(User $user): bool
    {
        return $user->hasRole('agent') && $user->agent !== null;
    }

    public function sync(User $user): bool
    {
        return $user->hasRole('agent') && $user->agent !== null;
    }

    public function acknowledge(User $user, Pointage $pointage): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin']);
    }
}
