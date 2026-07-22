<?php

namespace App\Http\Requests\Demandes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDemandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\AbsenceRequest::class) ?? false;
    }

    public function rules(): array
    {
        $types = ['ABSENCE', 'CONGE', 'PERMISSION', 'MALADIE', 'FORMATION', 'MISSION', 'CORRECTION', 'DEMISSION', 'RETRAITE'];

        return [
            'agent_id' => [
                Rule::requiredIf(fn () => $this->user()?->isAdminStaff()),
                'nullable',
                'integer',
                'exists:agents,id',
            ],
            'type_demande' => ['required', Rule::in($types)],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'heure_debut' => ['nullable', 'date_format:H:i', 'required_if:type_demande,PERMISSION'],
            'heure_fin' => ['nullable', 'date_format:H:i', 'required_if:type_demande,PERMISSION', 'after:heure_debut'],
            'motif' => ['required', 'string', 'max:2000'],
            'extra' => ['nullable', 'array'],
            'document_path' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'type_demande.in' => 'Type de demande invalide.',
            'date_fin.after_or_equal' => 'La date de fin doit être ≥ date de début.',
            'heure_debut.required_if' => 'Heure de début obligatoire pour une permission.',
            'document.mimes' => 'Document accepté : PDF, JPG, PNG, WEBP (max 5 Mo).',
        ];
    }
}
