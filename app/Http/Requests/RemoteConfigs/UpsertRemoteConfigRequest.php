<?php

namespace App\Http\Requests\RemoteConfigs;

use Illuminate\Foundation\Http\FormRequest;

class UpsertRemoteConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', \App\Models\RemoteConfig::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'key_name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'value_text' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
