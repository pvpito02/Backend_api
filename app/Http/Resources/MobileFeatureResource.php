<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MobileFeature */
class MobileFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->feature_key,
            'feature_key' => $this->feature_key,
            'label' => $this->label,
            'description' => $this->description,
            'visible' => (bool) $this->is_visible,
            'is_visible' => (bool) $this->is_visible,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
