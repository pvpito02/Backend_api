<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, Site $site): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, Site $site): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
