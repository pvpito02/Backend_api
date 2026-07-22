<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Missions\StoreMissionRequest;
use App\Http\Requests\Missions\UpdateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MissionController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Mission::class);

        $query = Mission::query()
            ->with(['agent', 'creator'])
            ->latest('date_debut');

        if ($request->user()->hasRole('agent')) {
            $query->where('agent_id', $request->user()->agent?->id);
        }

        if ($request->filled('agent_id') && ! $request->user()->hasRole('agent')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->filled('from')) {
            $query->whereDate('date_fin', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date_debut', '<=', $request->string('to'));
        }

        return MissionResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 15))))
        );
    }

    public function store(StoreMissionRequest $request): JsonResponse
    {
        $this->authorize('create', Mission::class);

        $data = $request->validated();
        $data['statut'] = $data['statut'] ?? 'PLANIFIEE';
        $data['created_by'] = $request->user()->id;

        $mission = Mission::query()->create($data)->load(['agent.user', 'creator']);

        if ($mission->agent?->user) {
            $this->notifications->notifyUser(
                $mission->agent->user,
                'Nouvelle mission',
                "Mission « {$mission->titre} » prévue à {$mission->lieu}.",
                'info',
                'mission',
                'Mission',
                $mission->id,
                playSound: true,
            );
        }

        return response()->json([
            'message' => 'Mission créée.',
            'mission' => new MissionResource($mission),
        ], 201);
    }

    public function show(Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);

        return response()->json([
            'mission' => new MissionResource($mission->load(['agent', 'creator'])),
        ]);
    }

    public function update(UpdateMissionRequest $request, Mission $mission): JsonResponse
    {
        $this->authorize('update', $mission);

        $oldStatut = $mission->statut;
        $mission->fill($request->validated())->save();
        $mission->load(['agent.user', 'creator']);

        if ($request->filled('statut') && $oldStatut !== $mission->statut && $mission->agent?->user) {
            $this->notifications->notifyUser(
                $mission->agent->user,
                'Mission mise à jour',
                "Statut de « {$mission->titre} » : {$mission->statut}.",
                'info',
                'mission',
                'Mission',
                $mission->id,
            );
        }

        return response()->json([
            'message' => 'Mission mise à jour.',
            'mission' => new MissionResource($mission),
        ]);
    }

    public function destroy(Mission $mission): JsonResponse
    {
        $this->authorize('delete', $mission);

        $mission->delete();

        return response()->json(['message' => 'Mission supprimée.']);
    }
}
