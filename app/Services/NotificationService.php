<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public function __construct(
        private readonly PushNotificationService $push,
        private readonly RealtimePublisher $realtime,
    ) {}

    public function notifyUser(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        ?string $categorie = null,
        ?string $relatedModel = null,
        ?int $relatedId = null,
        bool $playSound = false,
    ): AppNotification {
        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'categorie' => $categorie,
            'is_read' => false,
            'related_model' => $relatedModel,
            'related_id' => $relatedId,
            'play_sound' => $playSound,
        ]);

        $this->push->sendToUser($user, $title, $message, array_filter([
            'type' => $type,
            'categorie' => $categorie,
            'related_model' => $relatedModel,
            'related_id' => $relatedId,
            'notification_id' => $notification->id,
        ]));

        // Temps réel : le mobile / admin rafraîchit l’inbox en quelques secondes.
        $payload = array_filter([
            'resource' => 'notification',
            'id' => $notification->id,
            'user_id' => $user->id,
            'type' => $type,
            'categorie' => $categorie,
            'related_model' => $relatedModel,
            'related_id' => $relatedId,
            'play_sound' => $playSound,
        ], fn ($v) => $v !== null && $v !== '');

        $this->realtime->publish('notification.created', $payload, 'user', (int) $user->id);

        $agentId = $user->agent?->id;
        if ($agentId) {
            $this->realtime->publish('notification.created', $payload, 'agent', (int) $agentId);
        }

        return $notification;
    }

    /**
     * @param  Collection<int, User>|iterable<User>  $users
     */
    public function notifyMany(
        iterable $users,
        string $title,
        string $message,
        string $type = 'info',
        ?string $categorie = null,
        ?string $relatedModel = null,
        ?int $relatedId = null,
        bool $playSound = false,
    ): void {
        foreach ($users as $user) {
            $this->notifyUser($user, $title, $message, $type, $categorie, $relatedModel, $relatedId, $playSound);
        }
    }

    public function adminStaffUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'sous_admin']))
            ->get();
    }
}
