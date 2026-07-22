<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Site */
class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'radius_meters' => (float) $this->radius_meters,
            'qr_payload' => $this->qr_payload,
            'maps_url' => $this->maps_url,
            'services_rule' => $this->services_rule,
            'is_active' => (bool) $this->is_active,
            'departements' => $this->whenLoaded('departements', fn () => $this->departements->map(fn ($d) => [
                'id' => $d->id,
                'code' => $d->code,
                'nom' => $d->nom,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
