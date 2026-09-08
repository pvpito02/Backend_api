<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Base propre pour démarrage / déploiement :
     * rôles + config minimale + 3 comptes (super / admin / RH).
     * Pas de données de démo (agents, pointages, demandes…).
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepartementSeeder::class,
            SiteSeeder::class,
            WorkScheduleSeeder::class,
            MobileFeatureSeeder::class,
            RemoteConfigSeeder::class,
            HolidaySeeder::class,
            UserSeeder::class,
        ]);
    }
}
