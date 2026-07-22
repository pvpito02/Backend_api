<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    /**
     * Transforme un chemin storage relatif en URL publique absolue.
     * Ex: agents/avatars/x.jpg → http://localhost:8000/storage/agents/avatars/x.jpg
     */
    public static function public(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // Déjà une URL absolue (http/https) ou data URI
        if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        // Chemin déjà préfixé /storage/
        if (str_starts_with($path, '/storage/')) {
            return url($path);
        }

        if (str_starts_with($path, 'storage/')) {
            return url('/'.$path);
        }

        return Storage::disk('public')->url($path);
    }
}
