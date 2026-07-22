<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Announcement */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->content,
            'content' => $this->content,
            'when_label' => $this->when_label,
            'place' => $this->place,
            'image_path' => $this->image_url,
            'image_url' => MediaUrl::public($this->image_url),
            'published_at' => $this->published_at?->toIso8601String(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'ends_at' => $this->expires_at?->toIso8601String(),
            'duration_hours' => $this->duration_hours,
            'is_active' => (bool) $this->is_active,
            'active' => (bool) $this->is_active,
            'priority' => (int) $this->priority,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
