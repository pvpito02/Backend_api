<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OvertimeRequests\DecideOvertimeRequestRequest;
use App\Http\Requests\OvertimeRequests\StoreOvertimeRequestRequest;
use App\Http\Requests\OvertimeRequests\UpdateOvertimeRequestRequest;
use App\Http\Resources\OvertimeRequestResource;
use App\Models\OvertimeRequest;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OvertimeRequestController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OvertimeRequest::class);

        $query = OvertimeRequest::query()->with(['agent', 'approbateur'])->latest('id');

        if ($request->user()->hasRole('agent')) {
            $query->where('agent_id', $request->user()->agent?->id);
        }

        if ($request->filled('agent_id') && ! $request->user()->hasRole('agent')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        return OvertimeRequestResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 15))))
        );
    }

    public function store(StoreOvertimeRequestRequest $request): JsonResponse
    {
        $this->authorize('create', OvertimeRequest::class);

        $agentId = $request->user()->hasRole('agent')
            ? $request->user()->agent?->id
            : ($request->integer('agent_id') ?: null);

        if (! $agentId) {
            return response()->json(['message' => 'agent_id requis.'], 422);
        }

        $overtime = OvertimeRequest::query()->create([
            'agent_id' => $agentId,
            'date_travail' => $request->string('date_travail')->toString(),
            'heures_sup' => $request->input('heures_sup'),
            'motif' => $request->string('motif')->toString(),
            'statut' => 'EN_ATTENTE',
        ])->load(['agent', 'approbateur']);

        $this->notifications->notifyMany(
            $this->notifications->adminStaffUsers(),
            'Heures supplémentaires',
            "Nouvelle demande HS ({$overtime->heures_sup} h).",
            'confirmation',
            'heures_sup',
            'OvertimeRequest',
            $overtime->id,
            playSound: true,
        );

        $this->audit->log('overtime.create', $overtime);

        return response()->json([
            'message' => 'Demande d’heures supplémentaires créée.',
            'overtime' => new OvertimeRequestResource($overtime),
        ], 201);
    }

    public function show(OvertimeRequest $overtimeRequest): JsonResponse
    {
        $this->authorize('view', $overtimeRequest);

        return response()->json([
            'overtime' => new OvertimeRequestResource($overtimeRequest->load(['agent', 'approbateur'])),
        ]);
    }

    public function update(UpdateOvertimeRequestRequest $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $this->authorize('update', $overtimeRequest);

        $overtimeRequest->fill($request->validated())->save();

        $this->audit->log('overtime.update', $overtimeRequest);

        return response()->json([
            'message' => 'Demande HS mise à jour.',
            'overtime' => new OvertimeRequestResource($overtimeRequest->fresh()->load(['agent', 'approbateur'])),
        ]);
    }

    public function decide(DecideOvertimeRequestRequest $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $this->authorize('decide', $overtimeRequest);

        $decision = $request->string('decision')->toString();

        $overtimeRequest->load('agent.user');

        $overtimeRequest->forceFill([
            'statut' => $decision,
            'approuve_par' => $request->user()->id,
            'date_approbation' => now(),
            'commentaire' => $request->input('commentaire'),
        ])->save();

        if ($overtimeRequest->agent?->user) {
            $this->notifications->notifyUser(
                $overtimeRequest->agent->user,
                $decision === 'APPROUVEE' ? 'HS approuvées' : 'HS refusées',
                $decision === 'APPROUVEE'
                    ? "Vos heures supplémentaires du {$overtimeRequest->date_travail->format('d/m/Y')} ont été approuvées."
                    : "Vos heures supplémentaires du {$overtimeRequest->date_travail->format('d/m/Y')} ont été refusées.",
                $decision === 'APPROUVEE' ? 'approbation' : 'refus',
                'heures_sup',
                'OvertimeRequest',
                $overtimeRequest->id,
                playSound: true,
            );
        }

        $this->audit->log('overtime.decide', $overtimeRequest, ['decision' => $decision]);

        return response()->json([
            'message' => 'Décision enregistrée.',
            'overtime' => new OvertimeRequestResource($overtimeRequest->fresh()->load(['agent', 'approbateur'])),
        ]);
    }

    public function destroy(OvertimeRequest $overtimeRequest): JsonResponse
    {
        $this->authorize('delete', $overtimeRequest);

        $this->audit->log('overtime.delete', $overtimeRequest);
        $overtimeRequest->delete();

        return response()->json(['message' => 'Demande HS supprimée.']);
    }
}
