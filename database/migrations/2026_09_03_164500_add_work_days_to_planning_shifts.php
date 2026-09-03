<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_shifts', function (Blueprint $table) {
            $table->json('work_days')->nullable()->after('date_effective');
        });
    }

    public function down(): void
    {
        Schema::table('planning_shifts', function (Blueprint $table) {
            $table->dropColumn('work_days');
        });
    }
};
