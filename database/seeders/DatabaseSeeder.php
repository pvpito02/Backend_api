<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Données de référence réelles (alignées sur pointage_mairie_schema.sql).
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
            PlanningShiftSeeder::class,
            UserSeeder::class,
        ]);
    }
}
