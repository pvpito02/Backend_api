<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_shifts', function (Blueprint $table) {
            $table->string('audience', 32)->default('SERVICE')->after('departement_id');
            $table->foreignId('agent_id')->nullable()->after('audience')
                ->constrained('agents')->nullOnDelete();
            $table->index(['audience', 'date_effective']);
            $table->index(['agent_id', 'date_effective']);
        });
    }

    public function down(): void
    {
        Schema::table('planning_shifts', function (Blueprint $table) {
            $table->dropIndex(['audience', 'date_effective']);
            $table->dropIndex(['agent_id', 'date_effective']);
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn('audience');
        });
    }
};
