<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Squelette push FCM/APNs.
 * Enregistre les tokens et journalise les envois tant que les clés FCM ne sont pas configurées.
 */
class PushNotificationService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.fcm.enabled', false)
            && filled(config('services.fcm.server_key'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{sent: int, skipped: int, mode: string}
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('token');

        if ($tokens->isEmpty()) {
            return ['sent' => 0, 'skipped' => 0, 'mode' => 'none'];
        }

        if (! $this->isEnabled()) {
            Log::info('push.stub', [
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'tokens' => $tokens->count(),
                'data' => $data,
            ]);

            DeviceToken::query()
                ->whereIn('token', $tokens)
                ->update(['last_used_at' => now()]);

            return ['sent' => 0, 'skipped' => $tokens->count(), 'mode' => 'log'];
        }

        // Branche FCM réelle à brancher plus tard (HTTP v1 / legacy key).
        Log::warning('push.fcm_not_implemented', [
            'user_id' => $user->id,
            'tokens' => $tokens->count(),
        ]);

        return ['sent' => 0, 'skipped' => $tokens->count(), 'mode' => 'pending_fcm'];
    }
}
