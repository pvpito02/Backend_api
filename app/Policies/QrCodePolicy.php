<?php

namespace App\Policies;

use App\Models\QrCode;
use App\Models\User;

class QrCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, QrCode $qrCode): bool
    {
        if ($user->isAdminStaff()) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $qrCode->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, QrCode $qrCode): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, QrCode $qrCode): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function revoke(User $user, QrCode $qrCode): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
