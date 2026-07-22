<?php

namespace App\Http\Requests\Announcements;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('announcement')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'content' => ['sometimes', 'required', 'string', 'max:5000'],
            'body' => ['nullable', 'string', 'max:5000'],
            'when_label' => ['nullable', 'string', 'max:100'],
            'place' => ['nullable', 'string', 'max:150'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('body') && ! $this->filled('content')) {
            $this->merge(['content' => $this->input('body')]);
        }
    }
}
