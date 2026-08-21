<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('annonces.manage')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->staffCan('annonces.manage') || $user->hasRole('sous_admin');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->staffCan('annonces.manage') || $user->hasRole('sous_admin');
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->staffCan('annonces.manage') || $user->hasRole('sous_admin');
    }
}
