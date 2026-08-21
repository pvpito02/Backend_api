<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OvertimeRequests\DecideOvertimeRequestRequest;
use App\Http\Requests\OvertimeRequests\StoreOvertimeRequestRequest;
use App\Http\Requests\OvertimeRequests\UpdateOvertimeRequestRequest;
use App\Http\Resources\OvertimeRequestResource;
use App\Models\AppNotification;
use App\Models\OvertimeRequest;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OvertimeRequestController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
        private readonly RealtimePublisher $realtime,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OvertimeRequest::class);

        $query = OvertimeRequest::query()->with(['agent', 'approbateur'])->latest('id');

        $tokenName = $request->user()->currentAccessToken()?->name;
        if ($request->user()->isFieldUser()
            || $request->user()->shouldScopeToOwnAgent($tokenName)) {
            $query->where('agent_id', $request->user()->agent?->id);
        }

        if ($request->filled('agent_id')
            && ! $request->user()->isFieldUser()
            && ! $request->user()->shouldScopeToOwnAgent($tokenName)) {
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

        $actor = $request->user();
        $tokenName = strtolower((string) $actor->currentAccessToken()?->name);
        $isMobile = str_contains($tokenName, 'mobile') || str_contains($tokenName, 'pointage_mobile');

        if ($actor->isFieldUser()) {
            $agentId = $actor->agent?->id;
        } elseif ($request->filled('agent_id')) {
            $agentId = $request->integer('agent_id');
        } elseif ($isMobile && $actor->agent) {
            $agentId = $actor->agent->id;
        } else {
            $agentId = $actor->agent?->id;
        }

        if (! $agentId) {
            return response()->json(['message' => 'agent_id requis.'], 422);
        }

        if ($actor->hasRole('sous_admin')
            && (int) $agentId !== (int) $actor->agent?->id) {
            return response()->json(['message' => 'Vous ne pouvez soumettre une demande HS que pour votre propre fiche.'], 403);
        }

        $overtime = OvertimeRequest::query()->create([
            'agent_id' => $agentId,
            'date_travail' => $request->string('date_travail')->toString(),
            'heures_sup' => $request->input('heures_sup'),
            'motif' => $request->string('motif')->toString(),
            'statut' => 'EN_ATTENTE',
        ])->load(['agent.user', 'approbateur']);

        $isOwnRequest = $actor->agent?->id && (int) $actor->agent->id === (int) $agentId;
        $dateLabel = $overtime->date_travail->format('d/m/Y');
        $heuresLabel = rtrim(rtrim(number_format((float) $overtime->heures_sup, 2, ',', ' '), '0'), ',');

        if ($isOwnRequest || $actor->isFieldUser()) {
            $this->notifications->notifyMany(
                $this->notifications->recipientsForNewRequest($actor, (int) $agentId),
                'Heures supplémentaires',
                "Nouvelle demande HS ({$heuresLabel} h) pour le {$dateLabel}.",
                'confirmation',
                'heures_sup',
                'OvertimeRequest',
                $overtime->id,
                playSound: true,
                channel: AppNotification::CHANNEL_WEB,
            );
        } elseif ($overtime->agent?->user) {
            // Admin désigne un autre agent → l’agent est notifié.
            $motif = trim((string) $overtime->motif);
            $motifHint = $motif !== '' ? " Motif : {$motif}." : '';
            $this->notifications->notifyUser(
                $overtime->agent->user,
                'Heures supplémentaires assignées',
                "Vous avez été désigné pour {$heuresLabel} h supplémentaires le {$dateLabel}.{$motifHint}",
                'info',
                'heures_sup',
                'OvertimeRequest',
                $overtime->id,
                playSound: true,
            );
        }

        $this->audit->log('overtime.create', $overtime);

        $this->realtime->publishForAdminAndAgent('overtime.created', [
            'resource' => 'overtime',
            'id' => $overtime->id,
            'agent_id' => $overtime->agent_id,
            'statut' => $overtime->statut,
            'heures_sup' => $overtime->heures_sup,
        ], (int) $overtime->agent_id);

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

        $fresh = $overtimeRequest->fresh()->load(['agent', 'approbateur']);

        $this->realtime->publishForAdminAndAgent('overtime.updated', [
            'resource' => 'overtime',
            'id' => $fresh->id,
            'agent_id' => $fresh->agent_id,
            'statut' => $fresh->statut,
            'heures_sup' => $fresh->heures_sup,
        ], (int) $fresh->agent_id);

        return response()->json([
            'message' => 'Demande HS mise à jour.',
            'overtime' => new OvertimeRequestResource($fresh),
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

        $fresh = $overtimeRequest->fresh()->load(['agent', 'approbateur']);

        $this->realtime->publishForAdminAndAgent('overtime.updated', [
            'resource' => 'overtime',
            'id' => $fresh->id,
            'agent_id' => $fresh->agent_id,
            'statut' => $fresh->statut,
            'heures_sup' => $fresh->heures_sup,
            'decision' => $decision,
        ], (int) $fresh->agent_id);

        return response()->json([
            'message' => 'Décision enregistrée.',
            'overtime' => new OvertimeRequestResource($fresh),
        ]);
    }

    public function destroy(OvertimeRequest $overtimeRequest): JsonResponse
    {
        $this->authorize('delete', $overtimeRequest);

        $payload = [
            'resource' => 'overtime',
            'id' => $overtimeRequest->id,
            'agent_id' => $overtimeRequest->agent_id,
            'statut' => $overtimeRequest->statut,
        ];
        $agentId = (int) $overtimeRequest->agent_id;

        $this->audit->log('overtime.delete', $overtimeRequest);
        $overtimeRequest->delete();

        $this->realtime->publishForAdminAndAgent('overtime.deleted', $payload, $agentId);

        return response()->json(['message' => 'Demande HS supprimée.']);
    }
}
