<?php

namespace App\Http\Requests\Holidays;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Holiday::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:150'],
            'date_holiday' => ['required', 'date', 'unique:holidays,date_holiday'],
            'type_holiday' => ['required', Rule::in(['FERIE', 'JOURNALIER', 'SPECIAL', 'RELIGIEUX', 'MUNICIPAL'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_holiday.unique' => 'Un événement existe déjà à cette date.',
            'type_holiday.in' => 'Type invalide (FERIE, JOURNALIER, SPECIAL, RELIGIEUX, MUNICIPAL).',
        ];
    }
}
