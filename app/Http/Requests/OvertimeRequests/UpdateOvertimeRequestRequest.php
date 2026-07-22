<?php

namespace App\Http\Requests\OvertimeRequests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOvertimeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('overtime_request') ?? $this->route('overtimeRequest')) ?? false;
    }

    public function rules(): array
    {
        return [
            'date_travail' => ['sometimes', 'date', 'before_or_equal:today'],
            'heures_sup' => ['sometimes', 'numeric', 'min:0.5', 'max:24'],
            'motif' => ['sometimes', 'required', 'string', 'max:2000'],
        ];
    }
}
