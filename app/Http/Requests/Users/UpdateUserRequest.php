<?php

namespace App\Http\Requests\Users;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = $this->route('user');
        $user?->loadMissing('agent');

        return $this->user()?->can('update', $user) ?? false;
    }

    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = $this->route('user');
        $userId = $user->id;
        $agentId = $user->agent?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'confirmed', new StrongPassword],
            'role_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(function ($query) {
                    $allowed = $this->user()?->assignableRoleNames() ?? [];
                    $query->whereIn('name', $allowed);
                }),
            ],
            'permissions' => ['sometimes', 'nullable', 'array'],
            'permissions.*' => ['string', Rule::in(\App\Support\StaffPermissions::keys())],
            'avatar_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'matricule' => ['prohibited'],
            'prenom' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $this->isAgentRole())],
            'nom' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $this->isAgentRole())],
            'poste' => ['nullable', 'string', 'max:150'],
            'departement_id' => ['nullable', 'integer', 'exists:departements,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Cet email est déjà utilisé.',
            'role_id.exists' => 'Vous n’êtes pas autorisé à assigner ce rôle.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'matricule.prohibited' => 'Le matricule ne peut pas être modifié.',
        ];
    }

    private function isAgentRole(): bool
    {
        /** @var \App\Models\User $user */
        $user = $this->route('user');
        $roleId = $this->input('role_id', $user->role_id);
        $roleName = \App\Models\Role::query()->whereKey($roleId)->value('name');

        return $roleName === 'agent';
    }
}
