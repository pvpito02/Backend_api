<?php

namespace App\Http\Requests\Retraites;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRetraiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('retraite')) ?? false;
    }

    public function rules(): array
    {
        return [
            'date_depart' => ['sometimes', 'required', 'date'],
            'motif' => ['nullable', 'string', 'max:2000'],
            'statut' => ['sometimes', Rule::in(['EN_COURS', 'VALIDE', 'REJETE', 'TERMINE'])],
            'montant_pension' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
