<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('nom', 150);
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('email', 191)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('matricule', 30)->unique();
            $table->string('prenom', 100);
            $table->string('nom', 100);
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->date('date_naissance')->nullable();
            $table->date('date_entree')->nullable();
            $table->string('poste', 150)->nullable();
            $table->foreignId('departement_id')->nullable()->constrained('departements')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('supervisor_id')->nullable()->constrained('agents')->nullOnDelete()->cascadeOnUpdate();
            $table->string('email', 191)->nullable()->unique();
            $table->string('telephone', 30)->nullable();
            $table->string('photo_url', 255)->nullable();
            $table->enum('statut', ['Actif', 'Inactif', 'Retraité', 'Suspendu'])->default('Actif');
            $table->boolean('is_active')->default(true);
            $table->decimal('heure_travail_par_jour', 4, 2)->nullable()->default(8.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
        Schema::dropIfExists('departements');
    }
};
