<?php

namespace App\Policies;

use App\Models\AbsenceRequest;
use App\Models\User;

class AbsenceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, AbsenceRequest $demande): bool
    {
        if ($user->isAdminStaff()) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $demande->agent_id;
    }

    public function create(User $user): bool
    {
        // Admins web peuvent créer pour un agent ; terrain = sa propre fiche
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $user->isFieldUser() && $user->agent !== null;
    }

    public function update(User $user, AbsenceRequest $demande): bool
    {
        if ($user->isFieldUser() && $user->agent?->id === $demande->agent_id) {
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

        return $user->isFieldUser()
            && $user->agent?->id === $demande->agent_id
            && $demande->statut === 'EN_ATTENTE';
    }

    public function delete(User $user, AbsenceRequest $demande): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
