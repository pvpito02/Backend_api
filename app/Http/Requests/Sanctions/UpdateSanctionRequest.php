<?php

namespace App\Http\Requests\Sanctions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSanctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('sanction')) ?? false;
    }

    public function rules(): array
    {
        return [
            'type_sanction' => ['sometimes', Rule::in(['AVERTISSEMENT', 'LETTRE', 'SUSPENSION', 'AUTRE'])],
            'titre' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['nullable', 'date'],
            'severite' => ['sometimes', Rule::in(['faible', 'moyenne', 'elevee'])],
            'statut' => ['sometimes', Rule::in(['ACTIVE', 'TERMINEE', 'ANNULEE'])],
        ];
    }
}
