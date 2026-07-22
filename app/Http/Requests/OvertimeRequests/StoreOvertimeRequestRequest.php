<?php

namespace App\Http\Requests\OvertimeRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\OvertimeRequest::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'agent_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
            ],
            'date_travail' => ['required', 'date', 'before_or_equal:today'],
            'heures_sup' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'motif' => ['required', 'string', 'max:2000'],
        ];
    }
}
