<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\QrCode;
use Illuminate\Database\Seeder;

class QrCodeSeeder extends Seeder
{
    public function run(): void
    {
        $agents = Agent::query()->whereIn('matricule', ['EMP001', 'EMP002', 'EMP005'])->get();

        foreach ($agents as $agent) {
            QrCode::query()->updateOrCreate(
                [
                    'agent_id' => $agent->id,
                    'code' => "SANDIARA:{$agent->matricule}:DEMO",
                ],
                [
                    'issued_at' => now(),
                    'expires_at' => now()->addYear(),
                    'statut' => 'ACTIF',
                ]
            );
        }
    }
}
