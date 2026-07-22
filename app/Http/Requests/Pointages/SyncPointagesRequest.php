<?php

namespace App\Http\Requests\Pointages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPointagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sync', \App\Models\Pointage::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.client_id' => ['nullable', 'string', 'max:100'],
            'items.*.qr_payload' => ['nullable', 'string', 'max:150'],
            'items.*.site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'items.*.type' => ['nullable', Rule::in(['ENTREE', 'SORTIE'])],
            'items.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'items.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'items.*.device_id' => ['nullable', 'string', 'max:100'],
            'items.*.photo_path' => ['nullable', 'string', 'max:255'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'items.*.scanned_at' => ['required', 'date'],
        ];
    }
}
