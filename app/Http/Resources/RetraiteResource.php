<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Retraite */
class RetraiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'date_depart' => $this->date_depart?->format('Y-m-d'),
            'motif' => $this->motif,
            'statut' => $this->statut,
            'montant_pension' => $this->montant_pension !== null ? (float) $this->montant_pension : null,
            'created_by' => $this->created_by,
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'matricule' => $this->agent->matricule,
                'nom_complet' => $this->agent->nom_complet,
                'date_naissance' => $this->agent->date_naissance?->format('Y-m-d'),
                'date_entree' => $this->agent->date_entree?->format('Y-m-d'),
                'poste' => $this->agent->poste,
                'service' => $this->agent->departement?->nom,
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
