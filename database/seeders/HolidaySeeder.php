<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HolidaySeeder extends Seeder
{
    /**
     * Fêtes civiles fixes (Sénégal) pour l’année courante + l’année suivante.
     * Les fêtes religieuses variables (Tabaski, Korité, Magal…) se saisissent
     * dans le Calendrier officiel admin — elles changent chaque année.
     */
    public function run(): void
    {
        $now = now();
        $years = [(int) $now->year, (int) $now->year + 1];

        // Fêtes à date fixe (mois-jour)
        $fixed = [
            [1, 1, 'Jour de l’An'],
            [4, 4, 'Fête de l’Indépendance'],
            [5, 1, 'Fête du Travail'],
            [8, 15, 'Assomption'],
            [11, 1, 'Toussaint'],
            [12, 25, 'Noël'],
        ];

        $rows = [];
        $dates = [];
        foreach ($years as $year) {
            foreach ($fixed as [$month, $day, $label]) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $dates[] = $date;
                $rows[] = [
                    'libelle' => $label,
                    'date_holiday' => $date,
                    'type_holiday' => 'FERIE',
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Nettoie d’anciennes fêtes civiles seedées hors liste (même année)
        // sans toucher aux fêtes religieuses / spéciales saisies par l’admin.
        DB::table('holidays')
            ->where('type_holiday', 'FERIE')
            ->where(function ($q) use ($years) {
                foreach ($years as $year) {
                    $q->orWhereYear('date_holiday', $year);
                }
            })
            ->whereNotIn('date_holiday', $dates)
            ->delete();

        // Ancien seeder démo (dates religieuses figées incorrectes)
        DB::table('holidays')
            ->whereIn('date_holiday', ['2026-05-31', '2026-06-06'])
            ->where('type_holiday', 'RELIGIEUX')
            ->delete();

        DB::table('holidays')->upsert(
            $rows,
            ['date_holiday'],
            ['libelle', 'type_holiday', 'is_active', 'updated_at']
        );
    }
}
