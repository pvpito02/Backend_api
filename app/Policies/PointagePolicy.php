<?php

namespace App\Policies;

use App\Models\Pointage;
use App\Models\User;

class PointagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('pointages.manage')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, Pointage $pointage): bool
    {
        if ($user->staffCan('pointages.manage') || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $pointage->agent_id;
    }

    public function create(User $user): bool
    {
        // Scan terrain + saisie manuelle admin
        return $user->staffCan('pointages.manage')
            || $user->hasRole(['agent', 'conseiller']);
    }

    public function update(User $user, Pointage $pointage): bool
    {
        return $user->staffCan('pointages.manage');
    }

    public function delete(User $user, Pointage $pointage): bool
    {
        return $user->staffCan('pointages.manage');
    }

    public function scan(User $user): bool
    {
        return $user->canSelfPointage();
    }

    public function sync(User $user): bool
    {
        return $user->canSelfPointage();
    }

    public function acknowledge(User $user, Pointage $pointage): bool
    {
        return $user->staffCan('pointages.manage') || $user->hasRole('sous_admin');
    }
}
