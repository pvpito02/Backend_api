<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            // JSON array des jours travaillés, ex: [1,2,3,4,5] = lun-ven
            // null = hérite du work_schedule global
            $table->json('work_days')->nullable()->after('is_active');
            // Horaires custom optionnels (null = hérite du global)
            $table->time('entry_time')->nullable()->after('work_days');
            $table->time('exit_time')->nullable()->after('entry_time');
            $table->time('friday_exit_time')->nullable()->after('exit_time');
        });
    }

    public function down(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->dropColumn(['work_days', 'entry_time', 'exit_time', 'friday_exit_time']);
        });
    }
};
