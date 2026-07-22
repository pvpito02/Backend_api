<?php

namespace App\Http\Requests\AgentDocuments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\AgentDocument::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'agent_id' => [
                Rule::requiredIf(fn () => $this->user()?->isAdminStaff()),
                'nullable',
                'integer',
                'exists:agents,id',
            ],
            'type_document' => ['required', Rule::in(['PHOTO', 'CONTRAT', 'CNI', 'HISTORIQUE', 'AUTRE'])],
            'file' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'file_path' => ['nullable', 'string', 'max:255'],
            'is_present' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
