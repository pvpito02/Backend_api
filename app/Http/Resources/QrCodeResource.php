<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QrCode */
class QrCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'code' => $this->code,
            'payload' => $this->code,
            'image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&data='.urlencode($this->code),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'statut' => $this->statut,
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'matricule' => $this->agent->matricule,
                'nom_complet' => $this->agent->nom_complet,
                'poste' => $this->agent->poste,
                'photo_url' => MediaUrl::public($this->agent->photo_url),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
