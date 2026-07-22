<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\StoreAnnouncementRequest;
use App\Http\Requests\Announcements\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Services\MediaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Announcement::class);

        $query = Announcement::query()->latest('id');

        if ($request->boolean('active_only') || $request->user()->hasRole('agent')) {
            $query->activeForMobile()->orderByDesc('priority');
        }

        if ($request->has('is_active') && ! $request->user()->hasRole('agent')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('all')) {
            return AnnouncementResource::collection($query->get());
        }

        return AnnouncementResource::collection(
            $query->paginate(min(50, max(1, (int) $request->input('per_page', 15))))
        );
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);

        $data = $request->safe()->except(['body', 'image']);
        $data['content'] = $data['content'] ?? $request->input('body');
        $data['is_active'] = $data['is_active'] ?? true;
        $data['priority'] = $data['priority'] ?? 1;
        $data['published_at'] = now();
        $data['created_by'] = $request->user()->id;

        if ($request->filled('starts_at') && $request->filled('duration_hours') && ! $request->filled('expires_at')) {
            $data['expires_at'] = Carbon::parse($request->input('starts_at'))
                ->addHours((int) $request->input('duration_hours'));
        }

        if ($request->hasFile('image')) {
            $stored = $this->media->store($request->file('image'), 'announcement');
            $data['image_url'] = $stored['path'];
        }

        $announcement = Announcement::query()->create($data);

        return response()->json([
            'message' => 'Annonce publiée.',
            'announcement' => new AnnouncementResource($announcement),
        ], 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        return response()->json([
            'announcement' => new AnnouncementResource($announcement),
        ]);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('update', $announcement);

        $data = $request->safe()->except(['body', 'image']);

        if ($request->hasFile('image')) {
            if ($announcement->image_url) {
                $this->media->delete($announcement->image_url);
            }
            $stored = $this->media->store($request->file('image'), 'announcement');
            $data['image_url'] = $stored['path'];
        }

        $announcement->fill($data)->save();

        return response()->json([
            'message' => 'Annonce mise à jour.',
            'announcement' => new AnnouncementResource($announcement->fresh()),
        ]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        if ($announcement->image_url) {
            $this->media->delete($announcement->image_url);
        }

        $announcement->delete();

        return response()->json(['message' => 'Annonce supprimée.']);
    }
}
