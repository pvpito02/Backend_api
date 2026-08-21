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

    /**
     * Pool supervision web (hors auteur).
     *
     * @return Collection<int, User>
     */
    public function adminStaffUsers(?User $except = null): Collection
    {
        return $this->usersWithRoles(['super_admin', 'admin', 'sous_admin'], $except);
    }

    /**
     * Destinataires d’une nouvelle demande / HS selon l’auteur et la fiche ciblée.
     *
     * Circuit :
     * - Demande perso RH → Super uniquement (pas d’auto-notif)
     * - Demande perso sous-admin → Super + RH
     * - Demande perso Super → autres Super
     * - Agent / conseiller → staff (sauf auteur)
     * - Admin crée pour un autre agent → staff (sauf auteur)
     *
     * @return Collection<int, User>
     */
    public function recipientsForNewRequest(User $actor, int $agentId): Collection
    {
        $actor->loadMissing('role', 'agent');
        $isOwn = $actor->agent?->id !== null
            && (int) $actor->agent->id === (int) $agentId;

        if (! $isOwn) {
            return $this->adminStaffUsers($actor);
        }

        return $this->supervisorsForOwnRequest($actor);
    }

    /**
     * Qui peut / doit traiter une demande personnelle selon le rôle du propriétaire.
     *
     * @return Collection<int, User>
     */
    public function supervisorsForOwnRequest(User $owner): Collection
    {
        $owner->loadMissing('role');

        // RH → Super Admin uniquement
        if ($owner->hasRole(['admin', 'rh'])) {
            return $this->usersWithRoles(['super_admin'], $owner);
        }

        // Sous-admin / Direction → Super + RH
        if ($owner->hasRole(['sous_admin', 'direction'])) {
            return $this->usersWithRoles(['super_admin', 'admin'], $owner);
        }

        // Super qui soumet pour lui → autres Super
        if ($owner->hasRole('super_admin')) {
            return $this->usersWithRoles(['super_admin'], $owner);
        }

        // Agent / conseiller (et autres)
        return $this->adminStaffUsers($owner);
    }

    /**
     * Droits de décision selon le propriétaire de la fiche agent.
     */
    public function canDecideForOwner(?User $decider, ?User $owner): bool
    {
        if (! $decider) {
            return false;
        }

        if (! $owner) {
            // Fiche sans compte lié : supervision classique
            return $decider->hasRole(['super_admin', 'admin', 'sous_admin']);
        }

        $owner->loadMissing('role');

        if ($owner->hasRole(['admin', 'rh'])) {
            return $decider->hasRole('super_admin');
        }

        if ($owner->hasRole(['sous_admin', 'direction'])) {
            return $decider->hasRole(['super_admin', 'admin']);
        }

        if ($owner->hasRole('super_admin')) {
            return $decider->hasRole('super_admin') && $decider->id !== $owner->id;
        }

        return $decider->hasRole(['super_admin', 'admin', 'sous_admin']);
    }

    /**
     * @param  list<string>  $roles
     * @return Collection<int, User>
     */
    private function usersWithRoles(array $roles, ?User $except = null): Collection
    {
        $query = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', $roles));

        if ($except) {
            $query->where('id', '!=', $except->id);
        }

        return $query->get();
    }
}
