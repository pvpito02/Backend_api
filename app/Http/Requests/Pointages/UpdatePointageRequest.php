<?php

namespace App\Http\Requests\Pointages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePointageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('pointage')) ?? false;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'type' => ['sometimes', Rule::in(['ENTREE', 'SORTIE'])],
            'date_pointage' => ['sometimes', 'date'],
            'heure_pointage' => ['sometimes', 'date_format:H:i:s'],
            'statut' => ['sometimes', Rule::in(['A_L_HEURE', 'RETARD', 'ANOMALIE', 'VALIDE', 'MODIFIE'])],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_visitor' => ['sometimes', 'boolean'],
        ];
    }
}
