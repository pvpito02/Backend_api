<?php

namespace App\Http\Requests\WorkSchedules;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('work_schedule') ?? $this->route('workSchedule')) ?? false;
    }

    public function rules(): array
    {
        return [
            'entry_time' => ['sometimes', 'required', 'date_format:H:i'],
            'exit_time' => ['sometimes', 'required', 'date_format:H:i'],
            'friday_exit_time' => ['sometimes', 'required', 'date_format:H:i'],
            'late_tolerance_minutes' => ['sometimes', 'integer', 'min:0', 'max:180'],
            'work_saturday' => ['sometimes', 'boolean'],
            'block_sunday' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
