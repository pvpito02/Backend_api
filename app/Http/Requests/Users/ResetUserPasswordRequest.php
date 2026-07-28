<?php

namespace App\Http\Requests\Users;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
