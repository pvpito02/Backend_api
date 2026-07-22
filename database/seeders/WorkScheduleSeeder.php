<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('work_schedules')->upsert([
            [
                'name' => 'default',
                'entry_time' => '08:00:00',
                'exit_time' => '17:00:00',
                'friday_exit_time' => '13:00:00',
                'late_tolerance_minutes' => 15,
                'work_saturday' => 1,
                'block_sunday' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['name'], [
            'entry_time', 'exit_time', 'friday_exit_time', 'late_tolerance_minutes',
            'work_saturday', 'block_sunday', 'is_active', 'updated_at',
        ]);
    }
}
