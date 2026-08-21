<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin']);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasRole(['super_admin', 'admin', 'sous_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, User $model): bool
    {
        if ($model->hasRole('super_admin') && ! $user->hasRole('super_admin')) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($model->hasRole('super_admin') && ! $user->hasRole('super_admin')) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }
}
