<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Database\Seeder;

class SanctionSeeder extends Seeder
{
    public function run(): void
    {
        $agent = Agent::query()->where('matricule', 'EMP005')->first();
        $adminId = User::query()->where('email', 'admin@sandiara.sn')->value('id');

        if (! $agent) {
            return;
        }

        Sanction::query()->updateOrCreate(
            [
                'agent_id' => $agent->id,
                'titre' => 'Avertissement — retard récurrent',
            ],
            [
                'type_sanction' => 'AVERTISSEMENT',
                'description' => 'Retards répétés constatés sur le mois en cours.',
                'date_debut' => now()->subDays(7)->toDateString(),
                'date_fin' => null,
                'severite' => 'moyenne',
                'statut' => 'ACTIVE',
                'created_by' => $adminId,
            ]
        );
    }
}
