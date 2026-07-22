<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Seeder;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        $agent = Agent::query()->where('matricule', 'EMP001')->first();
        $adminId = User::query()->where('email', 'admin@sandiara.sn')->value('id');

        if (! $agent) {
            return;
        }

        Mission::query()->updateOrCreate(
            [
                'agent_id' => $agent->id,
                'titre' => 'Formation état civil — Fatick',
            ],
            [
                'description' => 'Atelier de digitalisation des actes.',
                'lieu' => 'Fatick',
                'date_debut' => now()->addDays(3)->toDateString(),
                'date_fin' => now()->addDays(4)->toDateString(),
                'statut' => 'PLANIFIEE',
                'created_by' => $adminId,
            ]
        );
    }
}
