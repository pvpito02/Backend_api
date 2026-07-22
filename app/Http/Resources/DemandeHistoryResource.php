<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DemandeStatusHistory */
class DemandeHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_statut' => $this->from_statut,
            'to_statut' => $this->to_statut,
            'by' => $this->changed_by_label,
            'changed_by' => $this->changed_by,
            'detail' => $this->detail,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
