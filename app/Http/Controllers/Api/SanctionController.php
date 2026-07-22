<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sanctions\StoreSanctionRequest;
use App\Http\Requests\Sanctions\UpdateSanctionRequest;
use App\Http\Resources\SanctionResource;
use App\Models\Sanction;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SanctionController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sanction::class);

        $query = Sanction::query()->with(['agent', 'creator'])->latest('id');

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->filled('type_sanction')) {
            $query->where('type_sanction', $request->string('type_sanction'));
        }

        return SanctionResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 15))))
        );
    }

    public function store(StoreSanctionRequest $request): JsonResponse
    {
        $this->authorize('create', Sanction::class);

        $data = $request->validated();
        $data['severite'] = $data['severite'] ?? 'moyenne';
        $data['statut'] = $data['statut'] ?? 'ACTIVE';
        $data['created_by'] = $request->user()->id;

        $sanction = Sanction::query()->create($data)->load(['agent.user', 'creator']);

        if ($sanction->agent?->user) {
            $this->notifications->notifyUser(
                $sanction->agent->user,
                'Sanction disciplinaire',
                "{$sanction->type_sanction} : {$sanction->titre}",
                'info',
                'sanction',
                'Sanction',
                $sanction->id,
                playSound: true,
            );
        }

        return response()->json([
            'message' => 'Sanction créée.',
            'sanction' => new SanctionResource($sanction),
        ], 201);
    }

    public function show(Sanction $sanction): JsonResponse
    {
        $this->authorize('view', $sanction);

        return response()->json([
            'sanction' => new SanctionResource($sanction->load(['agent', 'creator'])),
        ]);
    }

    public function update(UpdateSanctionRequest $request, Sanction $sanction): JsonResponse
    {
        $this->authorize('update', $sanction);

        $sanction->fill($request->validated())->save();

        return response()->json([
            'message' => 'Sanction mise à jour.',
            'sanction' => new SanctionResource($sanction->fresh()->load(['agent', 'creator'])),
        ]);
    }

    public function destroy(Sanction $sanction): JsonResponse
    {
        $this->authorize('delete', $sanction);

        $sanction->delete();

        return response()->json(['message' => 'Sanction supprimée.']);
    }
}
