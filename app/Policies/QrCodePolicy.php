<?php

namespace App\Policies;

use App\Models\QrCode;
use App\Models\User;

class QrCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('qr.manage')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, QrCode $qrCode): bool
    {
        if ($user->staffCan('qr.manage') || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $qrCode->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->staffCan('qr.manage');
    }

    public function update(User $user, QrCode $qrCode): bool
    {
        return $user->staffCan('qr.manage');
    }

    public function delete(User $user, QrCode $qrCode): bool
    {
        return $user->staffCan('qr.manage');
    }

    public function revoke(User $user, QrCode $qrCode): bool
    {
        return $user->staffCan('qr.manage');
    }
}
