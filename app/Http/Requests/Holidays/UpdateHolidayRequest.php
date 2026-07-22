<?php

namespace App\Http\Requests\Holidays;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('holiday')) ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('holiday');

        return [
            'libelle' => ['sometimes', 'required', 'string', 'max:150'],
            'date_holiday' => [
                'sometimes', 'required', 'date',
                Rule::unique('holidays', 'date_holiday')->ignore($id),
            ],
            'type_holiday' => ['sometimes', Rule::in(['FERIE', 'JOURNALIER', 'SPECIAL', 'RELIGIEUX', 'MUNICIPAL'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
