<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Departement */
class DepartementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'nom' => $this->nom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'responsable_id' => $this->responsable_id,
            'responsable' => $this->whenLoaded('responsable', fn () => $this->responsable ? [
                'id' => $this->responsable->id,
                'name' => $this->responsable->name,
                'email' => $this->responsable->email,
            ] : null),
            'agents_count' => $this->whenCounted('agents'),
            'agents' => AgentResource::collection($this->whenLoaded('agents')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
