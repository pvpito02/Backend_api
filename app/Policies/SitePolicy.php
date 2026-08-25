<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('sites.manage')
            || $user->hasRole(['sous_admin', 'conseiller', 'agent']);
    }

    public function view(User $user, Site $site): bool
    {
        return $user->staffCan('sites.manage')
            || $user->hasRole(['sous_admin', 'conseiller', 'agent']);
    }

    public function create(User $user): bool
    {
        return $user->staffCan('sites.manage');
    }

    public function update(User $user, Site $site): bool
    {
        return $user->staffCan('sites.manage');
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->staffCan('sites.manage');
    }
}
