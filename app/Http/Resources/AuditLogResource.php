<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'permission' => $this->permission,
            'summary' => $this->summary,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'details' => $this->details,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->role?->name,
                'role_label' => $this->user->role?->display_name ?? $this->user->role?->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
