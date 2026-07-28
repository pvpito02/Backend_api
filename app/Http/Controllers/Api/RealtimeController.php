<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Poll privé des événements temps réel (Sanctum).
 * Remplace un refresh manuel ; pas de données sensibles dans le flux.
 */
class RealtimeController extends Controller
{
    public function __construct(private readonly RealtimePublisher $realtime) {}

    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $data = $request->validate([
            'after' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $events = $this->realtime->pollFor(
            $user,
            isset($data['after']) ? (int) $data['after'] : 0,
            isset($data['limit']) ? (int) $data['limit'] : 50,
        );

        $lastId = $events === []
            ? (int) ($data['after'] ?? 0)
            : (int) $events[array_key_last($events)]['id'];

        return response()->json([
            'events' => $events,
            'last_id' => $lastId,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
