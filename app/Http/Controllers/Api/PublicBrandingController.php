<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RemoteConfig;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;

/**
 * Branding public (login / splash) — logo, nom, slogan depuis remote_configs.
 * Pas d’auth : données d’identité uniquement, pas de secrets.
 */
class PublicBrandingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $keys = ['app_name', 'org_name', 'tagline', 'logo_url', 'mobile_app_name', 'mobile_logo_url'];
        $rows = RemoteConfig::query()
            ->where('is_active', true)
            ->whereIn('key_name', $keys)
            ->get()
            ->keyBy('key_name');

        $value = static fn (string $key): ?string => $rows->get($key)?->value_text;

        $logoRaw = $value('logo_url');
        $mobileLogoRaw = $value('mobile_logo_url');
        $mobileName = trim((string) ($value('mobile_app_name') ?? ''));
        $appName = $value('app_name') ?: 'Système de Pointage QR';

        return response()->json([
            'app' => [
                'appName' => $appName,
                'orgName' => $value('org_name') ?: 'Mairie de Sandiara',
                'tagline' => $value('tagline') ?: 'Une commune green and clean',
                'logoUrl' => MediaUrl::public($logoRaw) ?? $logoRaw,
            ],
            // Identité affichée sur l’app agent (splash / login) — repli sur branding général
            'mobile' => [
                'appName' => $mobileName !== '' ? $mobileName : $appName,
                'logoUrl' => MediaUrl::public($mobileLogoRaw ?: $logoRaw) ?? ($mobileLogoRaw ?: $logoRaw),
            ],
        ]);
    }
}
