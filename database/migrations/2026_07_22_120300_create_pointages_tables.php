<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pointages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('type', ['ENTREE', 'SORTIE']);
            $table->date('date_pointage');
            $table->time('heure_pointage');
            $table->enum('statut', [
                'A_L_HEURE',
                'RETARD',
                'ANOMALIE',
                'VALIDE',
                'MODIFIE',
            ])->default('A_L_HEURE');
            $table->enum('source', ['QR', 'MANUEL', 'GPS', 'OFFLINE', 'AUTRE'])->default('QR');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('device_id', 100)->nullable();
            $table->boolean('is_visitor')->default(false);
            $table->boolean('pending_sync')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'date_pointage'], 'idx_pointages_agent_date');
            $table->index(['date_pointage', 'statut'], 'idx_pointages_date_statut');
        });

        Schema::create('pointage_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pointage_id')->constrained('pointages')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('type', 50);
            $table->enum('severite', ['faible', 'moyenne', 'elevee'])->default('moyenne');
            $table->text('description');
            $table->boolean('resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pointage_anomalies');
        Schema::dropIfExists('pointages');
    }
};
