<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('roles')->upsert([
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrateur',
                'description' => 'Gère tous les comptes, paramètres et validations',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrateur',
                'description' => 'Administration courante (RH, pointages, agents)',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'sous_admin',
                'display_name' => 'Sous-administrateur',
                'description' => 'Droits limités — consultation et actions courantes',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'agent',
                'display_name' => 'Agent',
                'description' => 'Pointage mobile, demandes et consultation personnelle',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'rh',
                'display_name' => 'RH',
                'description' => 'Alias historique — à mapper vers admin si besoin',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'direction',
                'display_name' => 'Direction',
                'description' => 'Vue globale — à mapper vers admin / super_admin si besoin',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['name'], ['display_name', 'description', 'is_active', 'updated_at']);
    }
}
