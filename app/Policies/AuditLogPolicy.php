<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Journal d’activité — consultation réservée au Super Admin (confidentialité).
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole('super_admin');
    }
}
