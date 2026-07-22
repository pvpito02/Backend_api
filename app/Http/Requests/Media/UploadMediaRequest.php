<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $folders = array_keys(\App\Services\MediaService::FOLDERS);

        return [
            'file' => ['required', 'file', 'max:5120'],
            'folder' => ['required', Rule::in($folders)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $folder = $this->input('folder');
            $file = $this->file('file');
            if (! $file) {
                return;
            }

            $mime = $file->getMimeType() ?? '';
            $isImage = str_starts_with($mime, 'image/');
            $isPdf = $mime === 'application/pdf';

            $imageFolders = ['avatar', 'agent_photo', 'pointage_photo', 'announcement', 'logo'];
            $docFolders = ['agent_document', 'demande_document'];

            if (in_array($folder, $imageFolders, true) && ! $isImage) {
                $validator->errors()->add('file', 'Ce dossier n’accepte que des images.');
            }

            if (in_array($folder, $docFolders, true) && ! $isImage && ! $isPdf) {
                $validator->errors()->add('file', 'Documents acceptés : image ou PDF.');
            }
        });
    }
}
