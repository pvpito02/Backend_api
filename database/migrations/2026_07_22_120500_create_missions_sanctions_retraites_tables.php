<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('titre', 150);
            $table->text('description')->nullable();
            $table->string('lieu', 150);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['PLANIFIEE', 'EN_COURS', 'TERMINEE', 'ANNULEE'])->default('PLANIFIEE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('type_sanction', ['AVERTISSEMENT', 'LETTRE', 'SUSPENSION', 'AUTRE']);
            $table->string('titre', 150);
            $table->text('description');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->enum('severite', ['faible', 'moyenne', 'elevee'])->default('moyenne');
            $table->enum('statut', ['ACTIVE', 'TERMINEE', 'ANNULEE'])->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('retraites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('date_depart');
            $table->text('motif')->nullable();
            $table->enum('statut', ['EN_COURS', 'VALIDE', 'REJETE', 'TERMINE'])->default('EN_COURS');
            $table->decimal('montant_pension', 10, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retraites');
        Schema::dropIfExists('sanctions');
        Schema::dropIfExists('missions');
    }
};
