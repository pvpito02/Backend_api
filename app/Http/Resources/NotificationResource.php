<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AppNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'categorie' => $this->categorie,
            'is_read' => (bool) $this->is_read,
            'read_at' => $this->read_at?->toIso8601String(),
            'related_model' => $this->related_model,
            'related_id' => $this->related_id,
            'play_sound' => (bool) $this->play_sound,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
