<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\RealtimeEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Publication d’événements temps réel — priorité vie privée.
 *
 * Règles :
 * - Auth obligatoire côté lecture (poll)
 * - Payload minimal : ids, type, statut — pas de mot de passe, token, email, téléphone
 * - Audience ciblée : admin staff / user.{id} / agent.{id}
 */
class RealtimePublisher
{
    /** Rétention courte (évite d’accumuler des données). */
    public const RETENTION_HOURS = 24;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $type, array $payload, string $audience = 'admin', ?int $audienceId = null): RealtimeEvent
    {
        $this->purgeOld();

        return RealtimeEvent::query()->create([
            'audience' => $audience,
            'audience_id' => $audienceId,
            'type' => $type,
            'payload' => $this->sanitize($payload),
            'created_at' => now(),
        ]);
    }

    /** Notifie le staff admin + éventuellement l’agent concerné. */
    public function publishForAdminAndAgent(string $type, array $payload, ?int $agentId): void
    {
        $this->publish($type, $payload, 'admin', null);

        if ($agentId) {
            $this->publish($type, $payload, 'agent', $agentId);

            $userId = Agent::query()->whereKey($agentId)->value('user_id');
            if ($userId) {
                $this->publish($type, $payload, 'user', (int) $userId);
            }
        }
    }

    /**
     * Événements visibles par l’utilisateur connecté (privé).
     *
     * @return list<array<string, mixed>>
     */
    public function pollFor(User $user, ?int $afterId = null, int $limit = 50): array
    {
        $afterId = max(0, (int) $afterId);
        $limit = min(100, max(1, $limit));

        $query = RealtimeEvent::query()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit);

        $query->where(function ($q) use ($user) {
            if ($user->isAdminStaff()) {
                $q->where(function ($q2) {
                    $q2->where('audience', 'admin')->whereNull('audience_id');
                });
            }

            $q->orWhere(function ($q2) use ($user) {
                $q2->where('audience', 'user')->where('audience_id', $user->id);
            });

            $agentId = $user->agent?->id;
            if ($agentId) {
                $q->orWhere(function ($q2) use ($agentId) {
                    $q2->where('audience', 'agent')->where('audience_id', $agentId);
                });
            }
        });

        return $query->get()->map(fn (RealtimeEvent $e) => [
            'id' => $e->id,
            'type' => $e->type,
            'payload' => $e->payload,
            'at' => $e->created_at?->toIso8601String(),
        ])->all();
    }

    /** @param  array<string, mixed>  $payload */
    private function sanitize(array $payload): array
    {
        $blocked = [
            'password', 'password_confirmation', 'token', 'plainTextToken',
            'email', 'phone', 'telephone', 'avatar_url', 'document_path',
            'photo_path', 'photo_url', 'current_password',
        ];

        $clean = [];
        foreach ($payload as $key => $value) {
            $k = strtolower((string) $key);
            if (in_array($k, $blocked, true)) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->sanitize($value);

                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        $clean['emitted_at'] = Carbon::now()->toIso8601String();

        return $clean;
    }

    private function purgeOld(): void
    {
        // Purge opportuniste (1 fois sur ~20) pour ne pas ralentir chaque publish
        if (random_int(1, 20) !== 1) {
            return;
        }

        RealtimeEvent::query()
            ->where('created_at', '<', now()->subHours(self::RETENTION_HOURS))
            ->delete();
    }
}
