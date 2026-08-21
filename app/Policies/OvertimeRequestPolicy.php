<?php

namespace App\Policies;

use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\NotificationService;

class OvertimeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('overtime.decide')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if ($user->staffCan('overtime.decide') || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $overtimeRequest->agent_id;
    }

    public function create(User $user): bool
    {
        if ($user->staffCan('overtime.decide')) {
            return true;
        }

        if ($user->isFieldUser() && $user->agent !== null) {
            return true;
        }

        return $user->isAdminStaff() && $user->agent !== null;
    }

    public function update(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if ($user->staffCan('overtime.decide')) {
            return true;
        }

        return $user->agent?->id === $overtimeRequest->agent_id
            && ($user->isFieldUser() || $user->isAdminStaff())
            && $overtimeRequest->statut === 'EN_ATTENTE';
    }

    public function decide(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if ($overtimeRequest->statut !== 'EN_ATTENTE') {
            return false;
        }

        $overtimeRequest->loadMissing('agent.user.role');

        if (! app(NotificationService::class)->canDecideForOwner(
            $user,
            $overtimeRequest->agent?->user,
        )) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->staffCan('overtime.decide');
    }

    public function delete(User $user, OvertimeRequest $overtimeRequest): bool
    {
        return $user->staffCan('overtime.decide');
    }
}
