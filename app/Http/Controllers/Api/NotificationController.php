<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AppNotification::class);

        $query = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id');

        $this->applyChannelFilter($query, $request);

        if ($request->has('unread_only') && $request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        return NotificationResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $query = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false);

        $this->applyChannelFilter($query, $request);

        return response()->json(['unread_count' => $query->count()]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        $notification->forceFill([
            'is_read' => true,
            'read_at' => now(),
            'play_sound' => false,
        ])->save();

        return response()->json([
            'message' => 'Notification lue.',
            'notification' => new NotificationResource($notification),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $query = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false);

        $this->applyChannelFilter($query, $request);

        $query->update([
            'is_read' => true,
            'read_at' => now(),
            'play_sound' => false,
        ]);

        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues.']);
    }

    /**
     * Canal demandé :
     * - mobile → mobile + both (jamais web-only)
     * - web → web + both
     * - absent → tout (rétrocompat)
     *
     * Si token Sanctum mobile et pas de paramètre : forcer mobile.
     *
     * @param  Builder<AppNotification>  $query
     */
    private function applyChannelFilter(Builder $query, Request $request): void
    {
        $channel = strtolower((string) $request->input('channel', ''));
        if ($channel === '') {
            $tokenName = $request->user()?->currentAccessToken()?->name;
            if ($request->user()?->shouldScopeToOwnAgent($tokenName)) {
                $channel = AppNotification::CHANNEL_MOBILE;
            }
        }

        if ($channel === AppNotification::CHANNEL_MOBILE) {
            $query->whereIn('channel', [
                AppNotification::CHANNEL_MOBILE,
                AppNotification::CHANNEL_BOTH,
            ]);
        } elseif ($channel === AppNotification::CHANNEL_WEB) {
            $query->whereIn('channel', [
                AppNotification::CHANNEL_WEB,
                AppNotification::CHANNEL_BOTH,
            ]);
        }
    }
}
