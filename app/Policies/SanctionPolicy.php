<?php

namespace App\Policies;

use App\Models\Sanction;
use App\Models\User;

class SanctionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('sanctions.manage')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, Sanction $sanction): bool
    {
        if ($user->staffCan('sanctions.manage') || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $sanction->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->staffCan('sanctions.manage');
    }

    public function update(User $user, Sanction $sanction): bool
    {
        return $user->staffCan('sanctions.manage');
    }

    public function delete(User $user, Sanction $sanction): bool
    {
        return $user->staffCan('sanctions.manage');
    }
}
