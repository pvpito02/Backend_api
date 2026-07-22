<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
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

        if ($request->has('unread_only') && $request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        return NotificationResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
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
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'play_sound' => false,
            ]);

        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues.']);
    }
}
