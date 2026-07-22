<?php

namespace App\Http\Requests\Announcements;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Announcement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:5000'],
            'body' => ['nullable', 'string', 'max:5000'],
            'when_label' => ['nullable', 'string', 'max:100'],
            'place' => ['nullable', 'string', 'max:150'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('content') && $this->filled('body')) {
            $this->merge(['content' => $this->input('body')]);
        }
    }
}
