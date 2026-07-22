<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MobileFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $features = [
            ['scan', 'Scanner QR', 'Pointage par QR code', 1, 10],
            ['historique', 'Historique', 'Voir les pointages passés', 1, 20],
            ['planning', 'Mon planning', 'Horaires et planning agent', 1, 30],
            ['demandes', 'Demandes', 'Absences / congés / permissions…', 1, 40],
            ['stats', 'Statistiques', 'Stats personnelles', 1, 50],
            ['profil', 'Profil', 'Fiche et photo agent', 1, 60],
            ['annonces', 'Annonces', 'Bannière infos mairie', 1, 70],
            ['missions', 'Missions', 'Déplacements hors site', 1, 80],
            ['carte', 'Carte GPS', 'Carte des sites de pointage', 1, 90],
            ['partage_rh', 'Partage RH', 'Envoyer un justificatif RH', 0, 100],
        ];

        DB::table('mobile_features')->upsert(
            array_map(fn (array $f) => [
                'feature_key' => $f[0],
                'label' => $f[1],
                'description' => $f[2],
                'is_visible' => $f[3],
                'sort_order' => $f[4],
                'created_at' => $now,
                'updated_at' => $now,
            ], $features),
            ['feature_key'],
            ['label', 'description', 'is_visible', 'sort_order', 'updated_at']
        );
    }
}
