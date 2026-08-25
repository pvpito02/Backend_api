<?php

namespace App\Http\Requests\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Agent $agent */
        $agent = $this->route('agent');

        return $this->user()?->can('update', $agent) ?? false;
    }

    public function rules(): array
    {
        /** @var \App\Models\Agent|int|string|null $routeAgent */
        $routeAgent = $this->route('agent');
        $agentId = $routeAgent instanceof \App\Models\Agent
            ? $routeAgent->getKey()
            : $routeAgent;

        return [
            // Matricule immutable après création (généré automatiquement).
            'matricule' => ['prohibited'],
            'prenom' => ['sometimes', 'required', 'string', 'max:100'],
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'sexe' => ['nullable', Rule::in(['M', 'F'])],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
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
            'matricule.prohibited' => 'Le matricule ne peut pas être modifié.',
            'supervisor_id.not_in' => 'Un agent ne peut pas être son propre superviseur.',
            'email.unique' => 'Cet email agent est déjà utilisé.',
            'statut.in' => 'Statut invalide (Actif, Inactif, Retraité, Suspendu).',
        ];
    }
}
