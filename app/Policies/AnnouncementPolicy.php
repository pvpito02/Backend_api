<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'conseiller', 'agent']);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'conseiller']);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'conseiller']);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'conseiller']);
    }
}
