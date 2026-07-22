<?php

namespace App\Http\Requests\Sites;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('site')) ?? false;
    }

    public function rules(): array
    {
        $siteId = $this->route('site');

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('sites', 'code')->ignore($siteId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'numeric', 'min:10', 'max:5000'],
            'qr_payload' => [
                'sometimes', 'required', 'string', 'max:150',
                Rule::unique('sites', 'qr_payload')->ignore($siteId),
            ],
            'maps_url' => ['nullable', 'url', 'max:255'],
            'services_rule' => ['sometimes', Rule::in(['ALL_EXCEPT_TECHNIQUE', 'TECHNIQUE_ONLY', 'ALL', 'CUSTOM'])],
            'is_active' => ['sometimes', 'boolean'],
            'departement_ids' => ['nullable', 'array'],
            'departement_ids.*' => ['integer', 'exists:departements,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => strtolower(trim($this->code))]);
        }
    }
}
