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
            ->with(['departement', 'agent'])
            ->where('is_active', true)
            ->orderBy('service_label');

        $user = $request->user();

        // Mobile terrain : service de l’agent OU planning personnel conseiller.
        if ($user && $user->isFieldUser()) {
            if ($user->hasRole('conseiller')) {
                $agentId = $user->agent?->id;
                if ($agentId === null) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('audience', 'CONSEILLERS')
                        ->where(function ($b) use ($agentId) {
                            $b->where('agent_id', (int) $agentId)
                                ->orWhereNull('agent_id');
                        });
                }
            } else {
                $deptId = $user->agent?->departement_id;
                if ($deptId === null) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('audience', 'SERVICE')
                        ->where('departement_id', (int) $deptId);
                }
            }
        } else {
            if ($request->filled('audience')) {
                $query->where('audience', strtoupper((string) $request->string('audience')));
            }
            if ($request->filled('departement_id')) {
                $query->where('departement_id', $request->integer('departement_id'));
            }
            if ($request->filled('agent_id')) {
                $query->where('agent_id', $request->integer('agent_id'));
            }
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->filled('from')) {
            $query->where(function ($b) use ($request) {
                $b->whereDate('date_effective', '>=', $request->date('from'))
                    ->orWhereNull('date_effective');
            });
        }

        if ($request->filled('to')) {
            $query->where(function ($b) use ($request) {
                $b->whereDate('date_effective', '<=', $request->date('to'))
                    ->orWhereNull('date_effective');
            });
        }

        if ($request->filled('dated_only') && $request->boolean('dated_only')) {
            $query->whereNotNull('date_effective');
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($b) use ($q) {
                $b->where('service_label', 'like', $q)
                    ->orWhere('manager_name', 'like', $q);
            });
        }

        return PlanningShiftResource::collection(
            $query->paginate(min(200, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function store(StorePlanningShiftRequest $request): JsonResponse
    {
        $this->authorize('create', PlanningShift::class);

        $data = $request->validated();
        $data['shift_start'] .= ':00';
        $data['shift_end'] .= ':00';
        $data['is_active'] = $data['is_active'] ?? true;
        $data['audience'] = strtoupper((string) ($data['audience'] ?? 'SERVICE'));

        if ($data['audience'] === 'CONSEILLERS') {
            $data['departement_id'] = null;
            $data['service_label'] = $data['service_label'] ?? 'Conseillers';
            if (empty($data['agent_id'])) {
                return response()->json([
                    'message' => 'Indiquez le conseiller concerné pour ce planning.',
                ], 422);
            }
        } else {
            $data['audience'] = 'SERVICE';
            $data['agent_id'] = null;
            if (empty($data['service_label']) && ! empty($data['departement_id'])) {
                $data['service_label'] = Departement::query()->whereKey($data['departement_id'])->value('nom');
            }
        }

        $shift = PlanningShift::query()->create($data)->load(['departement', 'agent']);

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

        if (isset($data['audience'])) {
            $data['audience'] = strtoupper((string) $data['audience']);
        }
        $audience = strtoupper((string) ($data['audience'] ?? $planningShift->audience ?? 'SERVICE'));
        if ($audience === 'CONSEILLERS') {
            $data['audience'] = 'CONSEILLERS';
            $data['departement_id'] = null;
            $data['service_label'] = $data['service_label'] ?? $planningShift->service_label ?? 'Conseillers';
            $agentId = $data['agent_id'] ?? $planningShift->agent_id;
            if (empty($agentId)) {
                return response()->json([
                    'message' => 'Indiquez le conseiller concerné pour ce planning.',
                ], 422);
            }
            $data['agent_id'] = (int) $agentId;
        } else {
            $data['audience'] = 'SERVICE';
            $data['agent_id'] = null;
            if (empty($data['service_label']) && ! empty($data['departement_id'])) {
                $data['service_label'] = Departement::query()->whereKey($data['departement_id'])->value('nom');
            }
        }

        $planningShift->fill($data)->save();
        $fresh = $planningShift->fresh()->load(['departement', 'agent']);

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
