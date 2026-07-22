<?php

namespace App\Http\Requests\MobileFeatures;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMobileFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('mobile_feature') ?? $this->route('mobileFeature')) ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_visible' => ['sometimes', 'boolean'],
            'visible' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('visible') && ! $this->has('is_visible')) {
            $this->merge(['is_visible' => $this->boolean('visible')]);
        }
    }
}
