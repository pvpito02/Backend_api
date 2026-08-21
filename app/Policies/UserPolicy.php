<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gouvernance comptes :
 * - Super : tout
 * - RH : selon permission utilisateurs.manage — agents / conseillers / sous-admins seulement
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->staffCan('utilisateurs.manage')
            || $user->hasRole('sous_admin');
    }

    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        if (! $this->viewAny($user)) {
            return false;
        }

        // RH / sous-admin : pas de fiche Super
        if (! $user->isSuperAdmin() && $model->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->staffCan('utilisateurs.manage');
    }

    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id && $user->hasRole(['super_admin', 'admin', 'rh'])) {
            return true;
        }

        if (! ($user->isSuperAdmin() || $user->staffCan('utilisateurs.manage'))) {
            return false;
        }

        return $user->canAdministerAccount($model);
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if (! ($user->isSuperAdmin() || $user->staffCan('utilisateurs.manage'))) {
            return false;
        }

        return $user->canAdministerAccount($model);
    }
}
