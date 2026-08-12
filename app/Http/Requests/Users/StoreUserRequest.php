<?php

namespace App\Http\Requests\Users;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'avatar_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            // Lier un agent RH déjà créé (sans compte) — pas de doublon
            'agent_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
                Rule::requiredIf(fn () => $this->isAgentRole()),
            ],
            'matricule' => ['prohibited'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'nom' => ['nullable', 'string', 'max:100'],
            'poste' => ['nullable', 'string', 'max:150'],
            'departement_id' => ['nullable', 'integer', 'exists:departements,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Cet email est déjà utilisé.',
            'role_id.exists' => 'Le rôle sélectionné est invalide.',
            'agent_id.required' => 'Sélectionnez un agent existant sans compte.',
            'agent_id.exists' => 'Agent introuvable.',
            'matricule.prohibited' => 'Le matricule est généré automatiquement.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }

    private function isAgentRole(): bool
    {
        $roleName = \App\Models\Role::query()->whereKey($this->integer('role_id'))->value('name');

        return $roleName === 'agent';
    }
}
