<?php

namespace App\Http\Requests\Sites;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Site::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9\-]+$/', 'unique:sites,code'],
            'name' => ['required', 'string', 'max:150'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'numeric', 'min:10', 'max:5000'],
            'qr_payload' => ['required', 'string', 'max:150', 'unique:sites,qr_payload'],
            'maps_url' => ['nullable', 'url', 'max:255'],
            'services_rule' => ['required', Rule::in(['ALL_EXCEPT_TECHNIQUE', 'TECHNIQUE_ONLY', 'ALL', 'CUSTOM'])],
            'is_active' => ['sometimes', 'boolean'],
            'departement_ids' => ['nullable', 'array'],
            'departement_ids.*' => ['integer', 'exists:departements,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Le code site doit être en minuscules (a-z, 0-9, tiret).',
            'qr_payload.unique' => 'Ce QR payload est déjà utilisé.',
            'services_rule.in' => 'Règle de services invalide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => strtolower(trim($this->code))]);
        }
    }
}
