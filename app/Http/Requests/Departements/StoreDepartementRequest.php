<?php

namespace App\Http\Requests\Departements;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Departement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_]+$/',
                'unique:departements,code',
            ],
            'nom' => ['required', 'string', 'max:150'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'email', 'max:191'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code du département est obligatoire.',
            'code.regex' => 'Le code doit être en majuscules (lettres, chiffres, underscore).',
            'code.unique' => 'Ce code de département existe déjà.',
            'nom.required' => 'Le nom du département est obligatoire.',
            'responsable_id.exists' => 'Le responsable sélectionné est invalide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => strtoupper(trim($this->code))]);
        }
    }
}
