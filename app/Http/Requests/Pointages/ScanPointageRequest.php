<?php

namespace App\Http\Requests\Pointages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScanPointageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('scan', \App\Models\Pointage::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'qr_payload' => ['nullable', 'string', 'max:150'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'type' => ['nullable', Rule::in(['ENTREE', 'SORTIE'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'scanned_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('qr_payload') && ! $this->filled('site_id') && ! $this->filled('latitude')) {
                $validator->errors()->add('qr_payload', 'Fournissez un QR, un site_id ou des coordonnées GPS.');
            }
        });
    }
}
