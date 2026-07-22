<?php

namespace App\Support;

class MediaUrl
{
    /**
     * Transforme un chemin storage relatif en chemin public /storage/...
     * (relatif — le client préfixe avec l’origine de l’API).
     *
     * Ex: branding/x.jpg → /storage/branding/x.jpg
     */
    public static function public(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // Data URI : laisser tel quel
        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        // URL absolue : extraire /storage/... si présent (évite APP_URL incorrect)
        if (preg_match('#^(https?:)?//#i', $path)) {
            if (preg_match('#(/storage/.+)$#', parse_url($path, PHP_URL_PATH) ?: '', $m)) {
                return $m[1];
            }

            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        // Asset front (ex. /logo_mairie.jpg)
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
