<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;

class RetraiteSeeder extends Seeder
{
    public function run(): void
    {
        // Dates de naissance pour alimenter GET /retraites/alerts
        Agent::query()->where('matricule', 'EMP001')->update([
            'date_naissance' => '1988-03-12',
            'date_entree' => '2015-01-15',
        ]);

        // ~61 ans en 2026 → « En cours de retraite » (âge min 60)
        Agent::query()->where('matricule', 'EMP003')->update([
            'date_naissance' => '1965-06-15',
            'date_entree' => '1990-01-10',
        ]);
    }
}
