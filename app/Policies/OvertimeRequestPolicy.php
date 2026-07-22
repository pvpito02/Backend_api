<?php

namespace App\Policies;

use App\Models\OvertimeRequest;
use App\Models\User;

class OvertimeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if ($user->hasRole(['super_admin', 'admin', 'sous_admin'])) {
            return true;
        }

        return $user->hasRole('agent') && $user->agent?->id === $overtimeRequest->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'agent'])
            && (! $user->hasRole('agent') || $user->agent !== null);
    }

    public function update(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $user->hasRole('agent')
            && $user->agent?->id === $overtimeRequest->agent_id
            && $overtimeRequest->statut === 'EN_ATTENTE';
    }

    public function decide(User $user, OvertimeRequest $overtimeRequest): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin'])
            && $overtimeRequest->statut === 'EN_ATTENTE';
    }

    public function delete(User $user, OvertimeRequest $overtimeRequest): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
