<?php

namespace App\Policies;

use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\NotificationService;

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
        // Admins web : pour un agent ; staff/terrain avec fiche : demande perso
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        if ($user->isFieldUser() && $user->agent !== null) {
            return true;
        }

        return $user->isAdminStaff() && $user->agent !== null;
    }

    public function update(User $user, AbsenceRequest $demande): bool
    {
        if ($user->agent?->id === $demande->agent_id
            && ($user->isFieldUser() || $user->isAdminStaff())
            && $demande->statut === 'EN_ATTENTE') {
            return true;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    public function decide(User $user, AbsenceRequest $demande): bool
    {
        if (! in_array($demande->statut, ['EN_ATTENTE', 'EN_COURS'], true)) {
            return false;
        }

        $demande->loadMissing('agent.user.role');

        return app(NotificationService::class)->canDecideForOwner(
            $user,
            $demande->agent?->user,
        );
    }

    public function cancel(User $user, AbsenceRequest $demande): bool
    {
        if (in_array($demande->statut, ['APPROUVEE', 'REJETEE', 'ANNULEE'], true)) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $user->agent?->id === $demande->agent_id
            && ($user->isFieldUser() || $user->isAdminStaff())
            && $demande->statut === 'EN_ATTENTE';
    }

    public function delete(User $user, AbsenceRequest $demande): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
