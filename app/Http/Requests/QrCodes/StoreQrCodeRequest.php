<?php

namespace App\Http\Requests\QrCodes;

use Illuminate\Foundation\Http\FormRequest;

class StoreQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\QrCode::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'code' => ['nullable', 'string', 'max:100', 'unique:qr_codes,code'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'revoke_previous' => ['sometimes', 'boolean'],
        ];
    }
}
