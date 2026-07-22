<?php

namespace App\Http\Requests\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Agent::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'matricule' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/i',
                'unique:agents,matricule',
            ],
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'sexe' => ['nullable', Rule::in(['M', 'F'])],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'date_entree' => ['nullable', 'date', 'before_or_equal:today'],
            'date_fin_contrat' => ['nullable', 'date', 'after_or_equal:date_entree'],
            'poste' => ['nullable', 'string', 'max:150'],
            'departement_id' => ['nullable', 'integer', 'exists:departements,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:agents,id', 'different:id'],
            'email' => ['nullable', 'email', 'max:191', 'unique:agents,email'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'photo_url' => ['nullable', 'string', 'max:255'],
            'statut' => ['sometimes', Rule::in(['Actif', 'Inactif', 'Retraité', 'Suspendu'])],
            'is_active' => ['sometimes', 'boolean'],
            'heure_travail_par_jour' => ['nullable', 'numeric', 'min:1', 'max:24'],
            'solde_conges' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:agents,user_id'],
            // Option : créer un compte user lié
            'create_user' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'required_if:create_user,true', 'string', 'confirmed', new \App\Rules\StrongPassword],
        ];
    }

    public function messages(): array
    {
        return [
            'matricule.required' => 'Le matricule est obligatoire.',
            'matricule.unique' => 'Ce matricule existe déjà.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'nom.required' => 'Le nom est obligatoire.',
            'sexe.in' => 'Le sexe doit être M ou F.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd’hui.',
            'departement_id.exists' => 'Le département sélectionné est invalide.',
            'supervisor_id.exists' => 'Le superviseur sélectionné est invalide.',
            'email.unique' => 'Cet email agent est déjà utilisé.',
            'user_id.unique' => 'Cet utilisateur est déjà lié à un agent.',
            'password.required_if' => 'Le mot de passe est obligatoire pour créer le compte.',
            'statut.in' => 'Statut invalide (Actif, Inactif, Retraité, Suspendu).',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('matricule') && is_string($this->matricule)) {
            $this->merge(['matricule' => strtoupper(trim($this->matricule))]);
        }
    }
}
