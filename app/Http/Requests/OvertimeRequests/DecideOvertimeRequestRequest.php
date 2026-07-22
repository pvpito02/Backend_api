<?php

namespace App\Http\Requests\OvertimeRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideOvertimeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('decide', $this->route('overtime_request') ?? $this->route('overtimeRequest')) ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['APPROUVEE', 'REFUSEE'])],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
