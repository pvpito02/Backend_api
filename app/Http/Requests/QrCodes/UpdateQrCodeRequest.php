<?php

namespace App\Http\Requests\QrCodes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('qr_code') ?? $this->route('qrCode')) ?? false;
    }

    public function rules(): array
    {
        return [
            'expires_at' => ['nullable', 'date'],
            'statut' => ['sometimes', Rule::in(['ACTIF', 'EXPIRE', 'REVOQUE'])],
        ];
    }
}
