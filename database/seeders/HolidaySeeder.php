<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $holidays = [
            ['Jour de l’An', '2026-01-01', 'FERIE'],
            ['Fête de la Victoire', '2026-05-01', 'FERIE'],
            ['Aïd al-Adha', '2026-05-31', 'FERIE'],
            ['Tabaski', '2026-06-06', 'FERIE'],
        ];

        DB::table('holidays')->upsert(
            array_map(fn (array $h) => [
                'libelle' => $h[0],
                'date_holiday' => $h[1],
                'type_holiday' => $h[2],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $holidays),
            ['date_holiday'],
            ['libelle', 'type_holiday', 'is_active', 'updated_at']
        );
    }
}
