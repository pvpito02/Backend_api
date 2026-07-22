<?php

namespace App\Policies;

use App\Models\Retraite;
use App\Models\User;

class RetraitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin']);
    }

    public function view(User $user, Retraite $retraite): bool
    {
        if ($user->hasRole(['super_admin', 'admin', 'sous_admin'])) {
            return true;
        }

        return $user->hasRole('agent') && $user->agent?->id === $retraite->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, Retraite $retraite): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Retraite $retraite): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function alerts(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin']);
    }
}
