<?php

namespace App\Policies;

use App\Models\Departement;
use App\Models\User;

class DepartementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, Departement $departement): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, Departement $departement): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Departement $departement): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
