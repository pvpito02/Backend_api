<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlanningShifts\StorePlanningShiftRequest;
use App\Http\Requests\PlanningShifts\UpdatePlanningShiftRequest;
use App\Http\Resources\PlanningShiftResource;
use App\Models\Departement;
use App\Models\PlanningShift;
use App\Services\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanningShiftController extends Controller
{
    public function __construct(private readonly RealtimePublisher $realtime) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PlanningShift::class);

        $query = PlanningShift::query()
            ->with('departement')
            ->where('is_active', true)
            ->orderBy('service_label');

        $user = $request->user();

        // Mobile terrain : uniquement le planning de son service (API sécurisée).
        if ($user && $user->isFieldUser()) {
            $deptId = $user->agent?->departement_id;
            if ($deptId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('departement_id', (int) $deptId);
            }
        } else {
            if ($request->filled('departement_id')) {
                $query->where('departement_id', $request->integer('departement_id'));
            }
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($b) use ($q) {
                $b->where('service_label', 'like', $q)
                    ->orWhere('manager_name', 'like', $q);
            });
        }

        return PlanningShiftResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function store(StorePlanningShiftRequest $request): JsonResponse
    {
        $this->authorize('create', PlanningShift::class);

        $data = $request->validated();
        $data['shift_start'] .= ':00';
        $data['shift_end'] .= ':00';
        $data['is_active'] = $data['is_active'] ?? true;

        if (empty($data['service_label']) && ! empty($data['departement_id'])) {
            $data['service_label'] = Departement::query()->whereKey($data['departement_id'])->value('nom');
        }

        $shift = PlanningShift::query()->create($data)->load('departement');

        $this->publishPlanningRealtime('planning.created', $shift, 'create');

        return response()->json([
            'message' => 'Quart de planning créé.',
            'planning_shift' => new PlanningShiftResource($shift),
        ], 201);
    }

    public function show(PlanningShift $planningShift): JsonResponse
    {
        $this->authorize('view', $planningShift);

        return response()->json([
            'planning_shift' => new PlanningShiftResource($planningShift->load('departement')),
        ]);
    }

    public function update(UpdatePlanningShiftRequest $request, PlanningShift $planningShift): JsonResponse
    {
        $this->authorize('update', $planningShift);

        $data = $request->validated();
        foreach (['shift_start', 'shift_end'] as $field) {
            if (isset($data[$field]) && strlen($data[$field]) === 5) {
                $data[$field] .= ':00';
            }
        }

        if (isset($data['required_count'], $data['assigned_count'])
            && $data['assigned_count'] > $data['required_count']) {
            return response()->json([
                'message' => 'Les équipes assignées ne peuvent pas dépasser le requis.',
            ], 422);
        }

        if (isset($data['assigned_count']) && ! isset($data['required_count'])
            && $data['assigned_count'] > $planningShift->required_count) {
            return response()->json([
                'message' => 'Les équipes assignées ne peuvent pas dépasser le requis.',
            ], 422);
        }

        $planningShift->fill($data)->save();
        $fresh = $planningShift->fresh()->load('departement');

        $this->publishPlanningRealtime('planning.updated', $fresh, 'update');

        return response()->json([
            'message' => 'Quart de planning mis à jour.',
            'planning_shift' => new PlanningShiftResource($fresh),
        ]);
    }

    public function destroy(PlanningShift $planningShift): JsonResponse
    {
        $this->authorize('delete', $planningShift);

        $id = $planningShift->id;
        $departementId = $planningShift->departement_id;
        $planningShift->delete();

        // Admins + agents connectés (filtrage côté mobile par departement_id).
        $this->realtime->publishForAll('planning.deleted', [
            'resource' => 'planning',
            'id' => $id,
            'departement_id' => $departementId,
            'action' => 'delete',
        ]);

        return response()->json(['message' => 'Quart de planning supprimé.']);
    }

    private function publishPlanningRealtime(string $event, PlanningShift $shift, string $action): void
    {
        $this->realtime->publishForAll($event, [
            'resource' => 'planning',
            'id' => $shift->id,
            'departement_id' => $shift->departement_id,
            'action' => $action,
        ]);
    }
}
