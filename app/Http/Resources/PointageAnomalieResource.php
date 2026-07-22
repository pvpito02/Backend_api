<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PointageAnomalie */
class PointageAnomalieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pointage_id' => $this->pointage_id,
            'type' => $this->type,
            'severite' => $this->severite,
            'description' => $this->description,
            'resolved' => (bool) $this->resolved,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
