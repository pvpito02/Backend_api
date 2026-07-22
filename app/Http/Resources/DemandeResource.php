<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AbsenceRequest */
class DemandeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'type_demande' => $this->type_demande,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin' => $this->date_fin?->format('Y-m-d'),
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'motif' => $this->motif,
            'extra' => $this->extra_json,
            'document_path' => $this->document_path,
            'document_url' => MediaUrl::public($this->document_path),
            'statut' => $this->statut,
            'lue_par_admin_at' => $this->lue_par_admin_at?->toIso8601String(),
            'motif_rejet' => $this->motif_rejet,
            'commentaire' => $this->commentaire,
            'date_approbation' => $this->date_approbation?->toIso8601String(),
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'matricule' => $this->agent->matricule,
                'nom_complet' => $this->agent->nom_complet,
                'poste' => $this->agent->poste,
                'service' => $this->agent->departement?->nom,
                'photo_url' => MediaUrl::public($this->agent->photo_url),
            ] : null),
            'approbateur' => $this->whenLoaded('approbateur', fn () => $this->approbateur ? [
                'id' => $this->approbateur->id,
                'name' => $this->approbateur->name,
            ] : null),
            'historique' => DemandeHistoryResource::collection($this->whenLoaded('history')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
