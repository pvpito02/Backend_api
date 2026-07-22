<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => MediaUrl::public($this->avatar_url),
            'avatar_path' => $this->avatar_url,
            'is_active' => (bool) $this->is_active,
            'role' => $this->whenLoaded('role', fn () => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'display_name' => $this->role->display_name,
            ] : null),
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'matricule' => $this->agent->matricule,
                'prenom' => $this->agent->prenom,
                'nom' => $this->agent->nom,
                'poste' => $this->agent->poste,
                'photo_url' => $this->agent->photo_url,
                'statut' => $this->agent->statut,
            ] : null),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_logout_at' => $this->last_logout_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
