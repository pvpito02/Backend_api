<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('permission', 30)->nullable()->after('action');
            $table->string('summary', 255)->nullable()->after('permission');
            $table->string('ip_address', 45)->nullable()->after('details');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
            $table->index(['permission', 'created_at'], 'idx_audit_permission_created');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_permission_created');
            $table->dropColumn(['permission', 'summary', 'ip_address', 'user_agent']);
        });
    }
};
