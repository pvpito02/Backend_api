<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PlanningShift */
class PlanningShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statutMap = [
            'CONFIRME' => 'Confirmé',
            'PROVISOIRE' => 'Provisoire',
            'EN_ATTENTE' => 'En attente',
        ];

        return [
            'id' => $this->id,
            'departement_id' => $this->departement_id,
            'audience' => $this->audience ?? 'SERVICE',
            'agent_id' => $this->agent_id,
            'service' => $this->service_label ?? $this->departement?->nom,
            'service_label' => $this->service_label,
            'shift' => $this->shift_label,
            'shift_start' => substr((string) $this->shift_start, 0, 8),
            'shift_end' => substr((string) $this->shift_end, 0, 8),
            'manager' => $this->manager_name,
            'manager_name' => $this->manager_name,
            'required' => (int) $this->required_count,
            'assigned' => (int) $this->assigned_count,
            'required_count' => (int) $this->required_count,
            'assigned_count' => (int) $this->assigned_count,
            'statut' => $this->statut,
            'status' => $statutMap[$this->statut] ?? $this->statut,
            'date_effective' => $this->date_effective?->format('Y-m-d'),
            'is_active' => (bool) $this->is_active,
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'nom_complet' => trim(($this->agent->prenom ?? '').' '.($this->agent->nom ?? '')),
                'matricule' => $this->agent->matricule,
            ] : null),
            'departement' => $this->whenLoaded('departement', fn () => $this->departement ? [
                'id' => $this->departement->id,
                'code' => $this->departement->code,
                'nom' => $this->departement->nom,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
