<?php

namespace App\Policies;

use App\Models\PlanningShift;
use App\Models\User;

class PlanningShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('planning.manage')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, PlanningShift $planningShift): bool
    {
        if ($user->staffCan('planning.manage') || $user->hasRole('sous_admin')) {
            return true;
        }

        if ($user->hasRole('conseiller')) {
            $agentId = $user->agent?->id;

            return ($planningShift->audience ?? 'SERVICE') === 'CONSEILLERS'
                && $agentId !== null
                && (
                    $planningShift->agent_id === null
                    || (int) $planningShift->agent_id === (int) $agentId
                );
        }

        if ($user->isFieldUser()) {
            $deptId = $user->agent?->departement_id;

            return ($planningShift->audience ?? 'SERVICE') === 'SERVICE'
                && $deptId !== null
                && (int) $deptId === (int) $planningShift->departement_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->staffCan('planning.manage') || $user->hasRole('sous_admin');
    }

    public function update(User $user, PlanningShift $planningShift): bool
    {
        return $user->staffCan('planning.manage') || $user->hasRole('sous_admin');
    }

    public function delete(User $user, PlanningShift $planningShift): bool
    {
        return $user->staffCan('planning.manage') || $user->hasRole('sous_admin');
    }
}
