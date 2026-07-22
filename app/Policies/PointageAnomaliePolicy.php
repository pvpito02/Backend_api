<?php

namespace App\Policies;

use App\Models\PointageAnomalie;
use App\Models\User;

class PointageAnomaliePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin']);
    }

    public function view(User $user, PointageAnomalie $anomalie): bool
    {
        if ($user->hasRole(['super_admin', 'admin', 'sous_admin'])) {
            return true;
        }

        return $user->hasRole('agent')
            && $user->agent?->id === $anomalie->pointage?->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function resolve(User $user, PointageAnomalie $anomalie): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin']);
    }
}
