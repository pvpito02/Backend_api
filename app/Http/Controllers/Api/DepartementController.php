<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departements\StoreDepartementRequest;
use App\Http\Requests\Departements\UpdateDepartementRequest;
use App\Http\Resources\DepartementResource;
use App\Models\Departement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Departement::class);

        $query = Departement::query()
            ->with(['responsable'])
            ->withCount('agents')
            ->orderBy('nom');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('nom', 'like', $q)
                    ->orWhere('code', 'like', $q)
                    ->orWhere('email', 'like', $q);
            });
        }

        if ($request->boolean('with_agents')) {
            $query->with(['agents' => fn ($q) => $q->orderBy('nom')]);
        }

        if ($request->boolean('all')) {
            return DepartementResource::collection($query->get());
        }

        return DepartementResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function store(StoreDepartementRequest $request): JsonResponse
    {
        $this->authorize('create', Departement::class);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $departement = Departement::query()->create($data);
        $departement->load('responsable')->loadCount('agents');

        return response()->json([
            'message' => 'Département créé.',
            'departement' => new DepartementResource($departement),
        ], 201);
    }

    public function show(Departement $departement): JsonResponse
    {
        $this->authorize('view', $departement);

        $departement->load(['responsable', 'agents'])->loadCount('agents');

        return response()->json([
            'departement' => new DepartementResource($departement),
        ]);
    }

    public function update(UpdateDepartementRequest $request, Departement $departement): JsonResponse
    {
        $this->authorize('update', $departement);

        $departement->fill($request->validated())->save();
        $departement->load('responsable')->loadCount('agents');

        return response()->json([
            'message' => 'Département mis à jour.',
            'departement' => new DepartementResource($departement),
        ]);
    }

    public function destroy(Departement $departement): JsonResponse
    {
        $this->authorize('delete', $departement);

        if ($departement->agents()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer : des agents sont encore rattachés à ce département.',
            ], 422);
        }

        $departement->delete();

        return response()->json([
            'message' => 'Département supprimé.',
        ]);
    }
}
