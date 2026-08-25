<?php

namespace App\Policies;

use App\Models\Retraite;
use App\Models\User;

class RetraitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('retraites.manage') || $user->hasRole('sous_admin');
    }

    public function view(User $user, Retraite $retraite): bool
    {
        if ($user->staffCan('retraites.manage') || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $retraite->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->staffCan('retraites.manage');
    }

    public function update(User $user, Retraite $retraite): bool
    {
        return $user->staffCan('retraites.manage');
    }

    public function delete(User $user, Retraite $retraite): bool
    {
        return $user->staffCan('retraites.manage');
    }

    public function alerts(User $user): bool
    {
        return $user->staffCan('retraites.manage') || $user->hasRole('sous_admin');
    }
}
