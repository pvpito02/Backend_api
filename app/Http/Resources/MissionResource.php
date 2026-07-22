<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Mission */
class MissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statutMap = [
            'PLANIFIEE' => 'Planifiée',
            'EN_COURS' => 'En cours',
            'TERMINEE' => 'Terminée',
            'ANNULEE' => 'Annulée',
        ];

        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'titre' => $this->titre,
            'description' => $this->description,
            'lieu' => $this->lieu,
            'dest' => $this->lieu,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin' => $this->date_fin?->format('Y-m-d'),
            'date' => $this->date_debut?->format('Y-m-d'),
            'statut' => $this->statut,
            'status' => $statutMap[$this->statut] ?? $this->statut,
            'created_by' => $this->created_by,
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'matricule' => $this->agent->matricule,
                'nom_complet' => $this->agent->nom_complet,
                'poste' => $this->agent->poste,
                'photo_url' => MediaUrl::public($this->agent->photo_url),
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
