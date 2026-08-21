<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // web = supervision admin uniquement ; mobile = parcours perso ; both = les deux
            $table->string('channel', 20)->default('both')->after('play_sound');
            $table->index(['user_id', 'channel', 'is_read'], 'idx_notifications_user_channel_read');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_channel_read');
            $table->dropColumn('channel');
        });
    }
};
