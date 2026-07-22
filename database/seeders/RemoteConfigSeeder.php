<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RemoteConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Clés alignées sur hello-world-hug-0971/src/routes/parametres.tsx (saveAll)
        $configs = [
            // Application
            ['app_name', 'Système de Pointage QR', 'Nom de l’application'],
            ['org_name', 'Mairie de Sandiara', 'Organisme'],
            ['tagline', 'Une commune green and clean', 'Slogan'],
            ['logo_url', '/logo_mairie.jpg', 'Logo application (admin / mobile)'],
            ['support_phone', '+221 33 XXX XX XX', 'Téléphone support'],
            ['support_email', 'rh@sandiara.sn', 'Email support'],
            ['maintenance_mode', '0', 'Mode maintenance mobile'],

            // Localisation / GPS
            ['gps_strict', '1', 'Géofencing strict'],
            ['offline_allowed', '1', 'Pointage hors-ligne autorisé'],
            ['require_photo_on_scan', '0', 'Photo obligatoire au scan'],
            ['default_radius_meters', '150', 'Rayon GPS par défaut'],
            ['mission_exception', '1', 'Exception GPS en mission'],

            // Sécurité admin
            ['session_minutes', '60', 'Durée de session admin'],
            ['max_login_attempts', '5', 'Tentatives de connexion max'],
            ['lock_minutes', '15', 'Verrouillage après échecs (minutes)'],
            ['force_password_change_days', '90', 'Forcer changement MDP (jours)'],
            ['min_password_length', '8', 'Longueur minimale du mot de passe'],
            ['require_2fa_admin', '0', '2FA obligatoire pour admins'],
            ['log_admin_connections', '1', 'Journaliser connexions admin'],

            // Sécurité mobile
            ['biometric_mobile', '1', 'Biométrie activable sur mobile'],
            ['pin_mobile', '0', 'PIN mobile activable'],

            // Avancé / notifications
            ['notif_retards', '1', 'Notifier les retards'],
            ['notif_daily_report', '1', 'Rapport journalier RH'],
            ['notif_absence', '1', 'Alerte absence'],
            ['notif_reminder_scan', '1', 'Rappel pointage'],
            ['force_app_update', '0', 'Forcer la mise à jour mobile'],
            ['app_version', '1.0.0', 'Version minimale mobile (minAppVersion)'],
            ['demo_mode', '1', 'Mode démonstration'],

            // Retraites (config admin /retraites)
            ['retraite_age_minimum', '60', 'Âge légal départ retraite'],
            ['retraite_age_limite', '65', 'Âge limite d’activité'],
            ['retraite_alerte_mois', '6,3,1', 'Seuils d’alerte retraite (mois)'],
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
