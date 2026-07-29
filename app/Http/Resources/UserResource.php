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
                'departement_id' => $this->agent->departement_id,
                'service' => $this->agent->departement?->nom,
                'telephone' => $this->agent->telephone,
                'email' => $this->agent->email,
                'photo_url' => MediaUrl::public($this->agent->photo_url) ?? $this->agent->photo_url,
                'photo_path' => $this->agent->photo_url,
                'qr_code' => $this->when(
                    $this->agent->relationLoaded('qrCodes'),
                    function () {
                        foreach ($this->agent->qrCodes->sortByDesc('id') as $qr) {
                            if ($qr->statut !== 'ACTIF') {
                                continue;
                            }
                            if ($qr->expires_at && $qr->expires_at->isPast()) {
                                continue;
                            }

                            return $qr->code;
                        }

                        return null;
                    }
                ),
                'date_naissance' => $this->agent->date_naissance?->format('Y-m-d'),
                'lieu_naissance' => $this->agent->lieu_naissance,
                'age' => $this->agent->age,
                'date_entree' => $this->agent->date_entree?->format('Y-m-d'),
                'solde_conges' => $this->agent->solde_conges !== null
                    ? (float) $this->agent->solde_conges
                    : null,
                'statut' => $this->agent->statut,
            ] : null),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_logout_at' => $this->last_logout_at?->toIso8601String(),
            // Présence réelle : au moins un token Sanctum actif récemment (multi-postes OK)
            'is_online' => $this->isOnline(),
            'sessions_count' => $this->activeSessionsCount(),
            'last_seen_at' => $this->lastSeenAt()?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
