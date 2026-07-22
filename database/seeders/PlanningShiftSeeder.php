<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanningShiftSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $etatCivilId = DB::table('departements')->where('code', 'ETAT_CIVIL')->value('id');
        $financesId = DB::table('departements')->where('code', 'FINANCES')->value('id');

        $rows = [
            [
                'departement_id' => $etatCivilId,
                'service_label' => 'État Civil',
                'shift_start' => '08:00:00',
                'shift_end' => '17:00:00',
                'manager_name' => 'Mme Diallo',
                'required_count' => 4,
                'assigned_count' => 4,
                'statut' => 'CONFIRME',
            ],
            [
                'departement_id' => $financesId,
                'service_label' => 'Finances',
                'shift_start' => '09:00:00',
                'shift_end' => '18:00:00',
                'manager_name' => 'M. Ndiaye',
                'required_count' => 3,
                'assigned_count' => 2,
                'statut' => 'PROVISOIRE',
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('planning_shifts')
                ->where('service_label', $row['service_label'])
                ->where('shift_start', $row['shift_start'])
                ->where('shift_end', $row['shift_end'])
                ->exists();

            if ($exists) {
                DB::table('planning_shifts')
                    ->where('service_label', $row['service_label'])
                    ->where('shift_start', $row['shift_start'])
                    ->where('shift_end', $row['shift_end'])
                    ->update(array_merge($row, ['is_active' => 1, 'updated_at' => $now]));
            } else {
                DB::table('planning_shifts')->insert(array_merge($row, [
                    'date_effective' => null,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
