<?php

namespace App\Policies;

use App\Models\AgentDocument;
use App\Models\User;

class AgentDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'sous_admin', 'agent']);
    }

    public function view(User $user, AgentDocument $agentDocument): bool
    {
        if ($user->hasRole(['super_admin', 'admin', 'sous_admin'])) {
            return true;
        }

        return $user->hasRole('agent') && $user->agent?->id === $agentDocument->agent_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'agent']);
    }

    public function update(User $user, AgentDocument $agentDocument): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, AgentDocument $agentDocument): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
