<?php

namespace App\Policies;

use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\NotificationService;

class AbsenceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('demandes.decide')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, AbsenceRequest $demande): bool
    {
        if ($user->staffCan('demandes.decide') || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $demande->agent_id;
    }

    public function create(User $user): bool
    {
        // Staff avec droit de décision : création pour un agent
        if ($user->staffCan('demandes.decide')) {
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

        return $user->staffCan('demandes.decide');
    }

    public function decide(User $user, AbsenceRequest $demande): bool
    {
        if (! in_array($demande->statut, ['EN_ATTENTE', 'EN_COURS'], true)) {
            return false;
        }

        $demande->loadMissing('agent.user.role');

        if (! app(NotificationService::class)->canDecideForOwner(
            $user,
            $demande->agent?->user,
        )) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->staffCan('demandes.decide');
    }

    public function cancel(User $user, AbsenceRequest $demande): bool
    {
        if (in_array($demande->statut, ['APPROUVEE', 'REJETEE', 'ANNULEE'], true)) {
            return false;
        }

        if ($user->staffCan('demandes.decide')) {
            return true;
        }

        return $user->agent?->id === $demande->agent_id
            && ($user->isFieldUser() || $user->isAdminStaff())
            && $demande->statut === 'EN_ATTENTE';
    }

    public function delete(User $user, AbsenceRequest $demande): bool
    {
        return $user->staffCan('demandes.decide');
    }
}
