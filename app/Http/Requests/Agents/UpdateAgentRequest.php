<?php

namespace App\Http\Requests\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    public function rules(): array
    {
        $agentId = $this->route('agent');

        return [
            'matricule' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/i',
                Rule::unique('agents', 'matricule')->ignore($agentId),
            ],
            'prenom' => ['sometimes', 'required', 'string', 'max:100'],
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'sexe' => ['nullable', Rule::in(['M', 'F'])],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'date_entree' => ['nullable', 'date', 'before_or_equal:today'],
            'date_fin_contrat' => ['nullable', 'date', 'after_or_equal:date_entree'],
            'poste' => ['nullable', 'string', 'max:150'],
            'departement_id' => ['nullable', 'integer', 'exists:departements,id'],
            'supervisor_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
                Rule::notIn([(int) $agentId]),
            ],
            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('agents', 'email')->ignore($agentId),
            ],
            'telephone' => ['nullable', 'string', 'max:30'],
            'photo_url' => ['nullable', 'string', 'max:255'],
            'statut' => ['sometimes', Rule::in(['Actif', 'Inactif', 'Retraité', 'Suspendu'])],
            'is_active' => ['sometimes', 'boolean'],
            'heure_travail_par_jour' => ['nullable', 'numeric', 'min:1', 'max:24'],
            'solde_conges' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('agents', 'user_id')->ignore($agentId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'matricule.unique' => 'Ce matricule existe déjà.',
            'supervisor_id.not_in' => 'Un agent ne peut pas être son propre superviseur.',
            'email.unique' => 'Cet email agent est déjà utilisé.',
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
