<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OvertimeRequest */
class OvertimeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'date_travail' => $this->date_travail?->format('Y-m-d'),
            'heures_sup' => (float) $this->heures_sup,
            'motif' => $this->motif,
            'statut' => $this->statut,
            'commentaire' => $this->commentaire,
            'date_approbation' => $this->date_approbation?->toIso8601String(),
            'approuve_par' => $this->approuve_par,
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'matricule' => $this->agent->matricule,
                'nom_complet' => $this->agent->nom_complet,
                'photo_url' => MediaUrl::public($this->agent->photo_url),
            ] : null),
            'approbateur' => $this->whenLoaded('approbateur', fn () => $this->approbateur ? [
                'id' => $this->approbateur->id,
                'name' => $this->approbateur->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
