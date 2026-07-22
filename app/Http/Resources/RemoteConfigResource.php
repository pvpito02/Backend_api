<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RemoteConfig */
class RemoteConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $value = $this->value_text;
        // logo_url → URL publique si chemin storage
        if ($this->key_name === 'logo_url') {
            $value = MediaUrl::public($value) ?? $value;
        }

        return [
            'id' => $this->id,
            'key_name' => $this->key_name,
            'value_text' => $this->value_text,
            'value' => $value,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
