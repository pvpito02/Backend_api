<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RemoteConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $configs = [
            ['app_name', 'Système de Pointage QR', 'Nom de l’application'],
            ['org_name', 'Mairie de Sandiara', 'Organisme'],
            ['tagline', 'Une commune green and clean', 'Slogan'],
            ['app_version', '1.0.0', 'Version minimale mobile'],
            ['force_app_update', '0', 'Forcer la mise à jour mobile'],
            ['maintenance_mode', '0', 'Mode maintenance mobile'],
            ['support_phone', '+221 33 XXX XX XX', 'Téléphone support'],
            ['support_email', 'rh@sandiara.sn', 'Email support'],
            ['gps_strict', '1', 'Géofencing strict'],
            ['offline_allowed', '1', 'Pointage hors-ligne autorisé'],
            ['require_photo_on_scan', '0', 'Photo obligatoire au scan'],
            ['default_radius_meters', '150', 'Rayon GPS par défaut'],
            ['mission_exception', '1', 'Exception GPS en mission'],
            ['notif_retards', '1', 'Notifier les retards'],
            ['notif_absence', '1', 'Alerte absence'],
            ['notif_reminder_scan', '1', 'Rappel pointage'],
            ['session_minutes', '60', 'Durée de session admin'],
            ['demo_mode', '1', 'Mode démonstration'],
        ];

        DB::table('remote_configs')->upsert(
            array_map(fn (array $c) => [
                'key_name' => $c[0],
                'value_text' => $c[1],
                'description' => $c[2],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $configs),
            ['key_name'],
            ['value_text', 'description', 'is_active', 'updated_at']
        );
    }
}
