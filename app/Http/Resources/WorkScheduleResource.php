<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkSchedule */
class WorkScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'entry_time' => substr((string) $this->entry_time, 0, 8),
            'exit_time' => substr((string) $this->exit_time, 0, 8),
            'friday_exit_time' => substr((string) $this->friday_exit_time, 0, 8),
            'late_tolerance_minutes' => (int) $this->late_tolerance_minutes,
            'work_saturday' => (bool) $this->work_saturday,
            'block_sunday' => (bool) $this->block_sunday,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
