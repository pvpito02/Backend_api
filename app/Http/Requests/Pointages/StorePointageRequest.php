<?php

namespace App\Http\Requests\Pointages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePointageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Pointage::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'type' => ['required', Rule::in(['ENTREE', 'SORTIE'])],
            'date_pointage' => ['required', 'date'],
            'heure_pointage' => ['required', 'date_format:H:i:s'],
            'statut' => ['nullable', Rule::in(['A_L_HEURE', 'RETARD', 'ANOMALIE', 'VALIDE', 'MODIFIE'])],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'source' => ['nullable', Rule::in(['QR', 'MANUEL', 'GPS', 'OFFLINE', 'AUTRE'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_visitor' => ['sometimes', 'boolean'],
        ];
    }
}
