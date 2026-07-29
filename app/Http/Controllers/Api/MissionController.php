<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Missions\StoreMissionRequest;
use App\Http\Requests\Missions\UpdateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Services\NotificationService;
use App\Services\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MissionController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly RealtimePublisher $realtime,
    ) {}

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
            $debut = $mission->date_debut?->format('d/m/Y') ?? '';
            $fin = $mission->date_fin?->format('d/m/Y') ?? '';
            $periode = $debut === $fin || $fin === ''
                ? $debut
                : "{$debut} → {$fin}";
            $this->notifications->notifyUser(
                $mission->agent->user,
                'Mission / déplacement assigné',
                "Vous avez été désigné pour « {$mission->titre} » à {$mission->lieu}"
                    .($periode !== '' ? " ({$periode})" : '').'.',
                'info',
                'mission',
                'Mission',
                $mission->id,
                playSound: true,
            );
        }

        $this->publishMissionEvent('mission.created', $mission);

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
        $oldAgentId = $mission->agent_id;
        $mission->fill($request->validated())->save();
        $mission->load(['agent.user', 'creator']);

        $agentReassigned = $oldAgentId !== $mission->agent_id;
        $statutChanged = $request->filled('statut') && $oldStatut !== $mission->statut;

        if ($mission->agent?->user) {
            if ($agentReassigned) {
                $debut = $mission->date_debut?->format('d/m/Y') ?? '';
                $fin = $mission->date_fin?->format('d/m/Y') ?? '';
                $periode = $debut === $fin || $fin === ''
                    ? $debut
                    : "{$debut} → {$fin}";
                $this->notifications->notifyUser(
                    $mission->agent->user,
                    'Mission / déplacement assigné',
                    "Vous avez été désigné pour « {$mission->titre} » à {$mission->lieu}"
                        .($periode !== '' ? " ({$periode})" : '').'.',
                    'info',
                    'mission',
                    'Mission',
                    $mission->id,
                    playSound: true,
                );
            } elseif ($statutChanged) {
                $this->notifications->notifyUser(
                    $mission->agent->user,
                    'Mission mise à jour',
                    "Statut de « {$mission->titre} » : {$mission->statut}.",
                    'info',
                    'mission',
                    'Mission',
                    $mission->id,
                    playSound: true,
                );
            }
        }

        $this->publishMissionEvent('mission.updated', $mission);

        return response()->json([
            'message' => 'Mission mise à jour.',
            'mission' => new MissionResource($mission),
        ]);
    }

    public function destroy(Mission $mission): JsonResponse
    {
        $this->authorize('delete', $mission);

        $payload = [
            'resource' => 'mission',
            'id' => $mission->id,
            'agent_id' => $mission->agent_id,
            'statut' => $mission->statut,
            'action' => 'delete',
        ];
        $agentId = (int) $mission->agent_id;

        $mission->delete();

        $this->realtime->publishForAdminAndAgent('mission.deleted', $payload, $agentId);

        return response()->json(['message' => 'Mission supprimée.']);
    }

    private function publishMissionEvent(string $type, Mission $mission): void
    {
        $this->realtime->publishForAdminAndAgent($type, [
            'resource' => 'mission',
            'id' => $mission->id,
            'agent_id' => $mission->agent_id,
            'statut' => $mission->statut,
        ], (int) $mission->agent_id);
    }
}
