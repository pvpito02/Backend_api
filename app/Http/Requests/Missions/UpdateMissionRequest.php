<?php

namespace App\Http\Requests\Missions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('mission')) ?? false;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['sometimes', 'integer', 'exists:agents,id'],
            'titre' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lieu' => ['sometimes', 'required', 'string', 'max:150'],
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['sometimes', 'date', 'after_or_equal:date_debut'],
            'statut' => ['sometimes', Rule::in(['PLANIFIEE', 'EN_COURS', 'TERMINEE', 'ANNULEE'])],
        ];
    }
}
