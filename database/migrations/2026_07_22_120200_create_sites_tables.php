<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('radius_meters', 8, 2)->default(150);
            $table->string('qr_payload', 150)->unique();
            $table->string('maps_url', 255)->nullable();
            $table->enum('services_rule', [
                'ALL_EXCEPT_TECHNIQUE',
                'TECHNIQUE_ONLY',
                'ALL',
                'CUSTOM',
            ])->default('ALL');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_departement', function (Blueprint $table) {
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('departement_id')->constrained('departements')->cascadeOnDelete();
            $table->primary(['site_id', 'departement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_departement');
        Schema::dropIfExists('sites');
    }
};
