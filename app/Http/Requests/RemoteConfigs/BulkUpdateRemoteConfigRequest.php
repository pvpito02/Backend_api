<?php

namespace App\Http\Requests\RemoteConfigs;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateRemoteConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', \App\Models\RemoteConfig::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'configs' => ['required', 'array', 'min:1'],
            'configs.*.key_name' => ['required', 'string', 'max:100'],
            'configs.*.value_text' => ['nullable', 'string', 'max:5000'],
            'configs.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
