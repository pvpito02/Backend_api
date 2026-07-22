<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Pointage */
class PointageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'site_id' => $this->site_id,
            'type' => $this->type,
            'date_pointage' => $this->date_pointage?->format('Y-m-d'),
            'heure_pointage' => is_string($this->heure_pointage)
                ? substr($this->heure_pointage, 0, 8)
                : $this->heure_pointage,
            'statut' => $this->statut,
            'late_minutes' => (int) ($this->late_minutes ?? 0),
            'source' => $this->source,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'device_id' => $this->device_id,
            'is_visitor' => (bool) $this->is_visitor,
            'pending_sync' => (bool) $this->pending_sync,
            'note' => $this->note,
            'photo_path' => $this->photo_path,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'acknowledged_by' => $this->acknowledged_by,
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'matricule' => $this->agent->matricule,
                'nom_complet' => $this->agent->nom_complet,
                'poste' => $this->agent->poste,
                'service' => $this->agent->departement?->nom,
            ] : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? [
                'id' => $this->site->id,
                'code' => $this->site->code,
                'name' => $this->site->name,
            ] : null),
            'anomalies' => PointageAnomalieResource::collection($this->whenLoaded('anomalies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
