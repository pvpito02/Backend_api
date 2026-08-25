<?php

namespace App\Policies;

use App\Models\AgentDocument;
use App\Models\User;

class AgentDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->staffCan('agents.manage')
            || $user->hasRole(['sous_admin', 'agent', 'conseiller']);
    }

    public function view(User $user, AgentDocument $agentDocument): bool
    {
        if ($user->staffCan('agents.manage') || $user->hasRole('sous_admin')) {
            return true;
        }

        return $user->isFieldUser() && $user->agent?->id === $agentDocument->agent_id;
    }

    public function create(User $user): bool
    {
        if ($user->staffCan('agents.manage')) {
            return true;
        }

        return $user->isFieldUser() && $user->agent !== null;
    }

    public function update(User $user, AgentDocument $agentDocument): bool
    {
        return $user->staffCan('agents.manage');
    }

    public function delete(User $user, AgentDocument $agentDocument): bool
    {
        return $user->staffCan('agents.manage');
    }
}
