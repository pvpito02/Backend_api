<?php

namespace App\Http\Requests\PlanningShifts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanningShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('planning_shift') ?? $this->route('planningShift')) ?? false;
    }

    public function rules(): array
    {
        return [
            'departement_id' => ['nullable', 'integer', 'exists:departements,id'],
            'service_label' => ['nullable', 'string', 'max:150'],
            'shift_start' => ['sometimes', 'required', 'date_format:H:i'],
            'shift_end' => ['sometimes', 'required', 'date_format:H:i'],
            'manager_name' => ['sometimes', 'required', 'string', 'max:150'],
            'required_count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'assigned_count' => ['sometimes', 'integer', 'min:0'],
            'statut' => ['sometimes', Rule::in(['CONFIRME', 'PROVISOIRE', 'EN_ATTENTE'])],
            'date_effective' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
