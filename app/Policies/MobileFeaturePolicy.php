<?php

namespace App\Policies;

use App\Models\MobileFeature;
use App\Models\User;

class MobileFeaturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, MobileFeature $mobileFeature): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MobileFeature $mobileFeature): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
