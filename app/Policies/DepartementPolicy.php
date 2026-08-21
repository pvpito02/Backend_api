<?php

namespace App\Policies;

use App\Models\Departement;
use App\Models\User;

class DepartementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('departements.manage')
            || $user->hasRole(['sous_admin', 'conseiller', 'agent']);
    }

    public function view(User $user, Departement $departement): bool
    {
        return $user->staffCan('departements.manage')
            || $user->hasRole(['sous_admin', 'conseiller', 'agent']);
    }

    public function create(User $user): bool
    {
        return $user->staffCan('departements.manage');
    }

    public function update(User $user, Departement $departement): bool
    {
        return $user->staffCan('departements.manage');
    }

    public function delete(User $user, Departement $departement): bool
    {
        return $user->staffCan('departements.manage');
    }
}
