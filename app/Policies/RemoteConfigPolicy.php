<?php

namespace App\Policies;

use App\Models\RemoteConfig;
use App\Models\User;

class RemoteConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, RemoteConfig $remoteConfig): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, ?RemoteConfig $remoteConfig = null): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, RemoteConfig $remoteConfig): bool
    {
        return $user->hasRole(['super_admin']);
    }
}
