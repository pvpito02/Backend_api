<?php

namespace App\Http\Requests\Missions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Mission::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lieu' => ['required', 'string', 'max:150'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'statut' => ['sometimes', Rule::in(['PLANIFIEE', 'EN_COURS', 'TERMINEE', 'ANNULEE'])],
        ];
    }
}
