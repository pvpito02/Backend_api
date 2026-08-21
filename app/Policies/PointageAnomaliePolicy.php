<?php

namespace App\Policies;

use App\Models\PointageAnomalie;
use App\Models\User;

class PointageAnomaliePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('pointages.manage') || $user->hasRole('sous_admin');
    }

    public function view(User $user, PointageAnomalie $anomalie): bool
    {
        if ($user->staffCan('pointages.manage') || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->isFieldUser()
            && $user->agent?->id === $anomalie->pointage?->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->staffCan('pointages.manage')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function resolve(User $user, PointageAnomalie $anomalie): bool
    {
        return $user->staffCan('pointages.manage') || $user->hasRole('sous_admin');
    }
}
