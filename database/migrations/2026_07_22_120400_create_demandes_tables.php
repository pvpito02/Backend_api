<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('type_demande', [
                'ABSENCE',
                'CONGE',
                'PERMISSION',
                'MALADIE',
                'FORMATION',
                'MISSION',
                'CORRECTION',
                'DEMISSION',
                'RETRAITE',
            ])->default('ABSENCE');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->text('motif');
            $table->json('extra_json')->nullable();
            $table->string('document_path', 255)->nullable();
            $table->enum('statut', [
                'EN_ATTENTE',
                'EN_COURS',
                'APPROUVEE',
                'REJETEE',
                'ANNULEE',
            ])->default('EN_ATTENTE');
            $table->timestamp('lue_par_admin_at')->nullable();
            $table->foreignId('lue_par_admin_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('approuve_par')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('date_approbation')->nullable();
            $table->text('motif_rejet')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'date_debut', 'date_fin'], 'idx_absence_agent_dates');
            $table->index('statut', 'idx_absence_statut');
        });

        Schema::create('demande_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absence_request_id')->constrained('absence_requests')->cascadeOnDelete();
            $table->string('from_statut', 30)->nullable();
            $table->string('to_statut', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_label', 150)->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['absence_request_id', 'created_at'], 'idx_dsh_request');
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('date_travail');
            $table->decimal('heures_sup', 4, 2);
            $table->text('motif');
            $table->enum('statut', ['EN_ATTENTE', 'APPROUVEE', 'REFUSEE'])->default('EN_ATTENTE');
            $table->foreignId('approuve_par')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('date_approbation')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('demande_status_history');
        Schema::dropIfExists('absence_requests');
    }
};
