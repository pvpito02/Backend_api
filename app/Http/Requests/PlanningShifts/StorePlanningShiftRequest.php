<?php

namespace App\Http\Requests\PlanningShifts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanningShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\PlanningShift::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'departement_id' => ['nullable', 'integer', 'exists:departements,id'],
            'audience' => ['nullable', Rule::in(['SERVICE', 'CONSEILLERS'])],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'service_label' => ['nullable', 'string', 'max:150'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i', 'after:shift_start'],
            'manager_name' => ['required', 'string', 'max:150'],
            'required_count' => ['required', 'integer', 'min:1', 'max:100'],
            'assigned_count' => ['required', 'integer', 'min:0', 'lte:required_count'],
            'statut' => ['required', Rule::in(['CONFIRME', 'PROVISOIRE', 'EN_ATTENTE'])],
            'date_effective' => ['nullable', 'date'],
            'work_days' => ['nullable', 'array', 'min:1', 'max:7'],
            'work_days.*' => ['integer', 'between:1,7'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $audience = strtoupper((string) ($this->input('audience') ?? 'SERVICE'));
            if ($audience === 'CONSEILLERS') {
                if (! $this->filled('agent_id')) {
                    $validator->errors()->add('agent_id', 'Sélectionnez le conseiller pour ce planning.');
                }

                return;
            }
            if (! $this->filled('departement_id') && ! $this->filled('service_label')) {
                $validator->errors()->add('service_label', 'Indiquez un département ou un libellé de service.');
            }
        });
    }
}
