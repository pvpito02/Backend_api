<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Holiday */
class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'title' => $this->libelle,
            'date_holiday' => $this->date_holiday?->format('Y-m-d'),
            'date' => $this->date_holiday?->format('Y-m-d'),
            'type_holiday' => $this->type_holiday,
            'type' => $this->type_holiday,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
