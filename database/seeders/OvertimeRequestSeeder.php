<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\OvertimeRequest;
use Illuminate\Database\Seeder;

class OvertimeRequestSeeder extends Seeder
{
    public function run(): void
    {
        $agent = Agent::query()->where('matricule', 'EMP001')->first();
        if (! $agent) {
            return;
        }

        OvertimeRequest::query()->updateOrCreate(
            [
                'agent_id' => $agent->id,
                'date_travail' => now()->subDays(2)->toDateString(),
            ],
            [
                'heures_sup' => 2.50,
                'motif' => 'Conseil municipal — prolongation',
                'statut' => 'EN_ATTENTE',
            ]
        );
    }
}
