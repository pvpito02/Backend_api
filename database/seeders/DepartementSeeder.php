<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartementSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            ['TECH', 'Service Technique', 'Maintenance et interventions techniques'],
            ['ETAT_CIVIL', 'État Civil', 'Gestion des actes et services administratifs'],
            ['INFORMATIQUE', 'Informatique', 'Support technique et digitalisation'],
            ['COURRIER', 'Bureau Courriel', 'Correspondance et organisation administrative'],
            ['URBANISME', 'Urbanisme', 'Planification et suivi urbain'],
            ['ACCUEIL', 'Accueil', 'Gestion du guichet et accueil du public'],
            ['ARCHIVES', 'Archives', 'Conservation et archivage'],
            ['FINANCES', 'Finances', 'Gestion budgétaire et comptabilité'],
            ['SECRETARIAT', 'Secrétariat', 'Appui de direction et organisation'],
            ['RH', 'Ressources humaines', 'Gestion du personnel'],
        ];

        DB::table('departements')->upsert(
            array_map(fn (array $r) => [
                'code' => $r[0],
                'nom' => $r[1],
                'description' => $r[2],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $rows),
            ['code'],
            ['nom', 'description', 'is_active', 'updated_at']
        );
    }
}
