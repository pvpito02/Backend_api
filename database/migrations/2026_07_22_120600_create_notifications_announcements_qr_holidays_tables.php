<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('title', 150);
            $table->text('message');
            $table->string('type', 50)->default('info');
            $table->string('categorie', 50)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('related_model', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->boolean('play_sound')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read'], 'idx_notifications_user_read');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('content');
            $table->string('when_label', 100)->nullable();
            $table->string('place', 150)->nullable();
            $table->string('image_url', 255)->nullable();
            $table->timestamp('published_at')->nullable()->useCurrent();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('duration_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('code', 100)->unique();
            $table->timestamp('issued_at')->nullable()->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->enum('statut', ['ACTIF', 'EXPIRE', 'REVOQUE'])->default('ACTIF');
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 150);
            $table->date('date_holiday')->unique();
            $table->enum('type_holiday', ['FERIE', 'JOURNALIER', 'SPECIAL'])->default('FERIE');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notifications');
    }
};
