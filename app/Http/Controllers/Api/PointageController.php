<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pointages\ReportAnomalieRequest;
use App\Http\Requests\Pointages\ScanPointageRequest;
use App\Http\Requests\Pointages\StorePointageRequest;
use App\Http\Requests\Pointages\SyncPointagesRequest;
use App\Http\Requests\Pointages\UpdatePointageRequest;
use App\Http\Resources\PointageAnomalieResource;
use App\Http\Resources\PointageResource;
use App\Models\Agent;
use App\Models\Pointage;
use App\Models\PointageAnomalie;
use App\Services\PointageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PointageController extends Controller
{
    public function __construct(private readonly PointageService $pointageService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Pointage::class);

        $query = Pointage::query()
            ->with(['agent.departement', 'site', 'anomalies'])
            ->orderByDesc('date_pointage')
            ->orderByDesc('heure_pointage')
            ->orderByDesc('id');

        // Agent : uniquement ses pointages
        if ($request->user()->hasRole('agent')) {
            $query->where('agent_id', $request->user()->agent?->id);
        }

        if ($request->filled('agent_id') && ! $request->user()->hasRole('agent')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->filled('date')) {
            $query->whereDate('date_pointage', $request->string('date'));
        }

        if ($request->filled('from')) {
            $query->whereDate('date_pointage', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date_pointage', '<=', $request->string('to'));
        }

        if ($request->boolean('retards_only')) {
            $query->where('statut', 'RETARD');
        }

        if ($request->boolean('unacknowledged')) {
            $query->where('statut', 'RETARD')->whereNull('acknowledged_at');
        }

        return PointageResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function scan(ScanPointageRequest $request): JsonResponse
    {
        $this->authorize('scan', Pointage::class);

        $agent = $request->user()->agent;
        if (! $agent) {
            return response()->json(['message' => 'Aucun profil agent lié à ce compte.'], 422);
        }

        $pointage = $this->pointageService->register([
            'agent' => $agent,
            'qr_payload' => $request->input('qr_payload'),
            'site_id' => $request->input('site_id'),
            'type' => $request->input('type'),
            'latitude' => $request->filled('latitude') ? (float) $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? (float) $request->input('longitude') : null,
            'source' => 'QR',
            'device_id' => $request->input('device_id'),
            'photo_path' => $request->input('photo_path'),
            'note' => $request->input('note'),
            'scanned_at' => $request->filled('scanned_at')
                ? Carbon::parse($request->input('scanned_at'))
                : now(),
            'pending_sync' => false,
        ]);

        return response()->json([
            'message' => 'Pointage enregistré.',
            'pointage' => new PointageResource($pointage),
        ], 201);
    }

    public function sync(SyncPointagesRequest $request): JsonResponse
    {
        $this->authorize('sync', Pointage::class);

        $agent = $request->user()->agent;
        if (! $agent) {
            return response()->json(['message' => 'Aucun profil agent lié à ce compte.'], 422);
        }

        $results = [];

        foreach ($request->input('items', []) as $item) {
            try {
                $pointage = $this->pointageService->register([
                    'agent' => $agent,
                    'qr_payload' => $item['qr_payload'] ?? null,
                    'site_id' => $item['site_id'] ?? null,
                    'type' => $item['type'] ?? null,
                    'latitude' => isset($item['latitude']) ? (float) $item['latitude'] : null,
                    'longitude' => isset($item['longitude']) ? (float) $item['longitude'] : null,
                    'source' => 'OFFLINE',
                    'device_id' => $item['device_id'] ?? null,
                    'photo_path' => $item['photo_path'] ?? null,
                    'note' => $item['note'] ?? null,
                    'scanned_at' => Carbon::parse($item['scanned_at']),
                    'pending_sync' => false,
                    'skip_geo' => false,
                ]);

                $results[] = [
                    'client_id' => $item['client_id'] ?? null,
                    'ok' => true,
                    'pointage' => new PointageResource($pointage),
                ];
            } catch (\Throwable $e) {
                $message = method_exists($e, 'errors')
                    ? collect($e->errors())->flatten()->first()
                    : $e->getMessage();

                $results[] = [
                    'client_id' => $item['client_id'] ?? null,
                    'ok' => false,
                    'error' => $message,
                ];
            }
        }

        return response()->json([
            'message' => 'Synchronisation terminée.',
            'results' => $results,
        ]);
    }

    public function store(StorePointageRequest $request): JsonResponse
    {
        $this->authorize('create', Pointage::class);

        // Saisie manuelle admin uniquement (pas le flux scan agent)
        if ($request->user()->hasRole('agent')) {
            return response()->json([
                'message' => 'Utilisez /api/pointages/scan pour pointer.',
            ], 403);
        }

        $data = $request->validated();
        $data['source'] = $data['source'] ?? 'MANUEL';
        $data['statut'] = $data['statut'] ?? 'A_L_HEURE';
        $data['late_minutes'] = $data['late_minutes'] ?? 0;

        $pointage = Pointage::query()->create($data)->load(['agent.departement', 'site']);

        return response()->json([
            'message' => 'Pointage créé (manuel).',
            'pointage' => new PointageResource($pointage),
        ], 201);
    }

    public function show(Pointage $pointage): JsonResponse
    {
        $this->authorize('view', $pointage);

        $pointage->load(['agent.departement', 'site', 'anomalies']);

        return response()->json([
            'pointage' => new PointageResource($pointage),
        ]);
    }

    public function update(UpdatePointageRequest $request, Pointage $pointage): JsonResponse
    {
        $this->authorize('update', $pointage);

        $data = $request->validated();
        $data['statut'] = $data['statut'] ?? 'MODIFIE';

        $pointage->fill($data)->save();
        $pointage->load(['agent.departement', 'site', 'anomalies']);

        return response()->json([
            'message' => 'Pointage mis à jour.',
            'pointage' => new PointageResource($pointage),
        ]);
    }

    public function destroy(Pointage $pointage): JsonResponse
    {
        $this->authorize('delete', $pointage);

        $pointage->delete();

        return response()->json(['message' => 'Pointage supprimé.']);
    }

    public function acknowledge(Pointage $pointage): JsonResponse
    {
        $this->authorize('acknowledge', $pointage);

        if ($pointage->statut !== 'RETARD') {
            return response()->json([
                'message' => 'Seuls les retards peuvent être marqués comme traités.',
            ], 422);
        }

        $pointage->forceFill([
            'acknowledged_at' => now(),
            'acknowledged_by' => request()->user()->id,
        ])->save();

        return response()->json([
            'message' => 'Retard marqué comme traité.',
            'pointage' => new PointageResource($pointage->fresh()->load(['agent.departement', 'site'])),
        ]);
    }

    public function reportAnomalie(ReportAnomalieRequest $request, Pointage $pointage): JsonResponse
    {
        $this->authorize('view', $pointage);
        $this->authorize('create', PointageAnomalie::class);

        $anomalie = PointageAnomalie::query()->create([
            'pointage_id' => $pointage->id,
            'type' => $request->string('type')->toString(),
            'severite' => $request->input('severite', 'moyenne'),
            'description' => $request->string('description')->toString(),
            'resolved' => false,
        ]);

        if ($pointage->statut !== 'ANOMALIE') {
            $pointage->update(['statut' => 'ANOMALIE']);
        }

        return response()->json([
            'message' => 'Anomalie signalée.',
            'anomalie' => new PointageAnomalieResource($anomalie),
        ], 201);
    }

    public function today(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Pointage::class);

        $agentId = $request->user()->hasRole('agent')
            ? $request->user()->agent?->id
            : ($request->integer('agent_id') ?: $request->user()->agent?->id);

        if (! $agentId) {
            return response()->json(['message' => 'agent_id requis.'], 422);
        }

        $agent = Agent::query()->findOrFail($agentId);
        if ($request->user()->hasRole('agent') && $request->user()->agent?->id !== $agent->id) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $items = Pointage::query()
            ->with('site')
            ->where('agent_id', $agent->id)
            ->whereDate('date_pointage', now()->toDateString())
            ->orderBy('heure_pointage')
            ->get();

        return response()->json([
            'date' => now()->toDateString(),
            'next_type' => $this->pointageService->nextType($agent, now()),
            'pointages' => PointageResource::collection($items),
        ]);
    }
}
