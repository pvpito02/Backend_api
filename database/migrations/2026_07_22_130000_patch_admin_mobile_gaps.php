<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Patch aligné admin (paramètres / planning / dossiers) + mobile (retard, photo scan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->date('date_fin_contrat')->nullable()->after('date_entree');
            $table->decimal('solde_conges', 5, 2)->nullable()->default(0)->after('heure_travail_par_jour');
        });

        Schema::table('pointages', function (Blueprint $table) {
            $table->unsignedInteger('late_minutes')->nullable()->default(0)->after('statut');
            $table->string('photo_path', 255)->nullable()->after('note');
            $table->timestamp('acknowledged_at')->nullable()->after('photo_path');
            $table->foreignId('acknowledged_by')->nullable()->after('acknowledged_at')
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('planning_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departement_id')->nullable()->constrained('departements')->nullOnDelete()->cascadeOnUpdate();
            $table->string('service_label', 150)->nullable();
            $table->time('shift_start');
            $table->time('shift_end');
            $table->string('manager_name', 150);
            $table->unsignedInteger('required_count')->default(1);
            $table->unsignedInteger('assigned_count')->default(0);
            $table->enum('statut', ['CONFIRME', 'PROVISOIRE', 'EN_ATTENTE'])->default('PROVISOIRE');
            $table->date('date_effective')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['departement_id', 'statut'], 'idx_planning_dept_statut');
        });

        Schema::create('agent_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('type_document', ['PHOTO', 'CONTRAT', 'CNI', 'HISTORIQUE', 'AUTRE']);
            $table->string('file_path', 255)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->boolean('is_present')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'type_document'], 'uq_agent_doc_type');
        });

        // Types calendrier admin : férié / religieux / municipal (MySQL only)
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE holidays MODIFY COLUMN type_holiday ENUM('FERIE','JOURNALIER','SPECIAL','RELIGIEUX','MUNICIPAL') NOT NULL DEFAULT 'FERIE'");
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE holidays MODIFY COLUMN type_holiday ENUM('FERIE','JOURNALIER','SPECIAL') NOT NULL DEFAULT 'FERIE'");
        }

        Schema::dropIfExists('agent_documents');
        Schema::dropIfExists('planning_shifts');

        Schema::table('pointages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn(['late_minutes', 'photo_path', 'acknowledged_at']);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['date_fin_contrat', 'solde_conges']);
        });
    }
};
