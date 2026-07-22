<?php

namespace App\Http\Requests\AgentDocuments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('agent_document') ?? $this->route('agentDocument')) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'file_path' => ['nullable', 'string', 'max:255'],
            'is_present' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
