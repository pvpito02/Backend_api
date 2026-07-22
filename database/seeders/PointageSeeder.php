<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Pointage;
use App\Models\Site;
use Illuminate\Database\Seeder;

class PointageSeeder extends Seeder
{
    public function run(): void
    {
        $siteNouvelle = Site::query()->where('code', 'nouvelle-mairie')->first();
        $siteAncienne = Site::query()->where('code', 'ancienne-mairie')->first();
        $agent = Agent::query()->where('matricule', 'EMP001')->first();
        $tech = Agent::query()->where('matricule', 'EMP005')->first();

        if (! $agent || ! $siteNouvelle) {
            return;
        }

        $today = now()->toDateString();

        Pointage::query()->updateOrCreate(
            [
                'agent_id' => $agent->id,
                'date_pointage' => $today,
                'type' => 'ENTREE',
                'heure_pointage' => '08:05:00',
            ],
            [
                'site_id' => $siteNouvelle->id,
                'statut' => 'A_L_HEURE',
                'late_minutes' => 0,
                'source' => 'QR',
                'latitude' => $siteNouvelle->latitude,
                'longitude' => $siteNouvelle->longitude,
                'is_visitor' => false,
                'pending_sync' => false,
            ]
        );

        if ($tech && $siteAncienne) {
            Pointage::query()->updateOrCreate(
                [
                    'agent_id' => $tech->id,
                    'date_pointage' => $today,
                    'type' => 'ENTREE',
                    'heure_pointage' => '08:35:00',
                ],
                [
                    'site_id' => $siteAncienne->id,
                    'statut' => 'RETARD',
                    'late_minutes' => 35,
                    'source' => 'QR',
                    'latitude' => $siteAncienne->latitude,
                    'longitude' => $siteAncienne->longitude,
                    'is_visitor' => false,
                    'pending_sync' => false,
                ]
            );
        }
    }
}
