<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->updateOrInsert(
            ['name' => 'conseiller'],
            [
                'display_name' => 'Conseiller',
                'description' => 'Conseiller municipal — consultation et suivi des dossiers',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'conseiller')->delete();
    }
};
