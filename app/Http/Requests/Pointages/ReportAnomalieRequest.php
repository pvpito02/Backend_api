<?php

namespace App\Http\Requests\Pointages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportAnomalieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\PointageAnomalie::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50'],
            'severite' => ['nullable', Rule::in(['faible', 'moyenne', 'elevee'])],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
