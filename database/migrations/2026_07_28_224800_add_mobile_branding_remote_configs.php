<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('remote_configs')->upsert(
            [
                [
                    'key_name' => 'mobile_app_name',
                    'value_text' => 'Pointage QR',
                    'description' => 'Nom de l’application mobile',
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key_name' => 'mobile_logo_url',
                    'value_text' => '',
                    'description' => 'Logo de l’application mobile (vide = logo général)',
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['key_name'],
            ['description', 'is_active', 'updated_at']
        );
    }

    public function down(): void
    {
        DB::table('remote_configs')
            ->whereIn('key_name', ['mobile_app_name', 'mobile_logo_url'])
            ->delete();
    }
};
