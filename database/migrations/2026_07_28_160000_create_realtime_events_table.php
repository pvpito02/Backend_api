<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File d’événements temps réel privés (auth Sanctum).
 * Pas de canal public : chaque event a une audience ciblée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realtime_events', function (Blueprint $table) {
            $table->id();
            /** admin | user | agent */
            $table->string('audience', 20);
            /** null pour admin ; user_id ou agent_id selon audience */
            $table->unsignedBigInteger('audience_id')->nullable()->index();
            /** ex. demande.created, pointage.created */
            $table->string('type', 80)->index();
            /** Payload minimal (ids / statut) — jamais de secrets */
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['audience', 'audience_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realtime_events');
    }
};
