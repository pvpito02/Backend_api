<?php

namespace App\Http\Requests\Sanctions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSanctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Sanction::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'type_sanction' => ['required', Rule::in(['AVERTISSEMENT', 'LETTRE', 'SUSPENSION', 'AUTRE'])],
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'severite' => ['sometimes', Rule::in(['faible', 'moyenne', 'elevee'])],
            'statut' => ['sometimes', Rule::in(['ACTIVE', 'TERMINEE', 'ANNULEE'])],
        ];
    }
}
