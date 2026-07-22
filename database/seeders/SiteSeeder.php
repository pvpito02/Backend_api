<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('sites')->upsert([
            [
                'code' => 'nouvelle-mairie',
                'name' => 'Nouvelle Mairie',
                'latitude' => 14.4359287,
                'longitude' => -16.7972649,
                'radius_meters' => 150.00,
                'qr_payload' => 'SANDIARA:BORNE:NOUVELLE-MAIRIE',
                'maps_url' => 'https://maps.app.goo.gl/E5CbMQK4gVc46PNC9',
                'services_rule' => 'ALL_EXCEPT_TECHNIQUE',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ancienne-mairie',
                'name' => 'Ancienne Mairie',
                'latitude' => 14.4361428,
                'longitude' => -16.7926273,
                'radius_meters' => 150.00,
                'qr_payload' => 'SANDIARA:BORNE:ANCIENNE-MAIRIE',
                'maps_url' => 'https://maps.app.goo.gl/nSqHCdRw4rNibXhFA',
                'services_rule' => 'TECHNIQUE_ONLY',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], [
            'name', 'latitude', 'longitude', 'radius_meters', 'qr_payload',
            'maps_url', 'services_rule', 'is_active', 'updated_at',
        ]);
    }
}
