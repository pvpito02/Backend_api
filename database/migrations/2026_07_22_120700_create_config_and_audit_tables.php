<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key_name', 100)->unique();
            $table->text('value_text')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('mobile_features', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key', 50)->unique();
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->default('default')->unique();
            $table->time('entry_time')->default('08:00:00');
            $table->time('exit_time')->default('17:00:00');
            $table->time('friday_exit_time')->default('13:00:00');
            $table->unsignedInteger('late_tolerance_minutes')->default(15);
            $table->boolean('work_saturday')->default(true);
            $table->boolean('block_sunday')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->enum('platform', ['android', 'ios', 'web'])->default('android');
            $table->string('device_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active'], 'idx_device_user');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('action', 100);
            $table->string('model_type', 100)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'idx_audit_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('mobile_features');
        Schema::dropIfExists('remote_configs');
    }
};
