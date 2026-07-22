<?php

namespace App\Http\Requests\Demandes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDemandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('decide', $this->route('demande')) ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['APPROUVEE', 'REJETEE'])],
            'motif_rejet' => ['nullable', 'required_if:decision,REJETEE', 'string', 'max:2000'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.in' => 'Décision invalide (APPROUVEE ou REJETEE).',
            'motif_rejet.required_if' => 'Le motif de rejet est obligatoire.',
        ];
    }
}
