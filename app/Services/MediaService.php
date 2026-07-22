<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public const FOLDERS = [
        'avatar' => 'avatars',
        'agent_photo' => 'agents/photos',
        'agent_document' => 'agents/documents',
        'demande_document' => 'demandes/documents',
        'pointage_photo' => 'pointages/photos',
        'announcement' => 'announcements',
        'logo' => 'branding',
    ];

    /**
     * @return array{path: string, url: string, original_name: string, mime: string, size: int}
     */
    public function store(UploadedFile $file, string $folderKey): array
    {
        $folder = self::FOLDERS[$folderKey] ?? 'uploads';
        $ext = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $name = Str::uuid()->toString().'.'.strtolower($ext);

        $path = $file->storeAs($folder, $name, 'public');

        return [
            'path' => $path,
            'url' => '/storage/'.$path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
        ];
    }

    public function delete(?string $path): void
    {
        if (! $path || preg_match('#^https?://#i', $path)) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
