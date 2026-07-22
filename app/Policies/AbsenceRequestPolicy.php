<?php

namespace App\Policies;

use App\Models\AbsenceRequest;
use App\Models\User;

class AbsenceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, AbsenceRequest $demande): bool
    {
        if ($user->hasRole(['super_admin', 'admin', 'sous_admin'])) {
            return true;
        }

        return $user->hasRole('agent') && $user->agent?->id === $demande->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'agent'])
            && (! $user->hasRole('agent') || $user->agent !== null);
    }

    public function update(User $user, AbsenceRequest $demande): bool
    {
        // Agent peut modifier seulement si encore EN_ATTENTE
        if ($user->hasRole('agent') && $user->agent?->id === $demande->agent_id) {
            return $demande->statut === 'EN_ATTENTE';
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    public function decide(User $user, AbsenceRequest $demande): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin'])
            && in_array($demande->statut, ['EN_ATTENTE', 'EN_COURS'], true);
    }

    public function cancel(User $user, AbsenceRequest $demande): bool
    {
        if (in_array($demande->statut, ['APPROUVEE', 'REJETEE', 'ANNULEE'], true)) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $user->hasRole('agent')
            && $user->agent?->id === $demande->agent_id
            && $demande->statut === 'EN_ATTENTE';
    }

    public function delete(User $user, AbsenceRequest $demande): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
