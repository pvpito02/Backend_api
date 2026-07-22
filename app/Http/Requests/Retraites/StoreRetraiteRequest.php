<?php

namespace App\Http\Requests\Retraites;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRetraiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Retraite::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id', 'unique:retraites,agent_id'],
            'date_depart' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:2000'],
            'statut' => ['sometimes', Rule::in(['EN_COURS', 'VALIDE', 'REJETE', 'TERMINE'])],
            'montant_pension' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'agent_id.unique' => 'Un dossier retraite existe déjà pour cet agent.',
        ];
    }
}
