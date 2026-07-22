<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Agent */
class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'matricule' => $this->matricule,
            'prenom' => $this->prenom,
            'nom' => $this->nom,
            'nom_complet' => $this->nom_complet,
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance?->format('Y-m-d'),
            'date_entree' => $this->date_entree?->format('Y-m-d'),
            'date_fin_contrat' => $this->date_fin_contrat?->format('Y-m-d'),
            'poste' => $this->poste,
            'departement_id' => $this->departement_id,
            'service' => $this->whenLoaded('departement', fn () => $this->departement?->nom),
            'departement' => $this->whenLoaded('departement', fn () => $this->departement ? [
                'id' => $this->departement->id,
                'code' => $this->departement->code,
                'nom' => $this->departement->nom,
            ] : null),
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->whenLoaded('supervisor', fn () => $this->supervisor ? [
                'id' => $this->supervisor->id,
                'matricule' => $this->supervisor->matricule,
                'nom_complet' => $this->supervisor->nom_complet,
            ] : null),
            'email' => $this->email,
            'telephone' => $this->telephone,
            'photo_url' => $this->photo_url,
            'statut' => $this->statut,
            'is_active' => (bool) $this->is_active,
            'heure_travail_par_jour' => $this->heure_travail_par_jour !== null
                ? (float) $this->heure_travail_par_jour
                : null,
            'solde_conges' => $this->solde_conges !== null ? (float) $this->solde_conges : null,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'is_active' => (bool) $this->user->is_active,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
