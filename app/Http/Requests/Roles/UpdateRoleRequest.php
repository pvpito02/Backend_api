<?php

namespace App\Http\Requests\Roles;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');
        $id = $role?->id;

        $rules = [
            'display_name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        // Slug modifiable uniquement pour les rôles non système
        if ($role && ! $role->isSystem()) {
            $rules['name'] = [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->ignore($id),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Le code doit être en minuscules (lettres, chiffres, underscore), sans espace.',
            'name.unique' => 'Ce code de rôle existe déjà.',
        ];
    }
}
