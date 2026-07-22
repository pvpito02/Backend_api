<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\StoreSiteRequest;
use App\Http\Requests\Sites\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Site::class);

        $query = Site::query()->with('departements')->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('all')) {
            return SiteResource::collection($query->get());
        }

        return SiteResource::collection(
            $query->paginate(min(50, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function store(StoreSiteRequest $request): JsonResponse
    {
        $this->authorize('create', Site::class);

        $site = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['departement_ids']);
            $data['radius_meters'] = $data['radius_meters'] ?? 150;
            $data['is_active'] = $data['is_active'] ?? true;

            $site = Site::query()->create($data);

            if ($request->filled('departement_ids')) {
                $site->departements()->sync($request->input('departement_ids'));
            }

            return $site->load('departements');
        });

        return response()->json([
            'message' => 'Site créé.',
            'site' => new SiteResource($site),
        ], 201);
    }

    public function show(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        return response()->json([
            'site' => new SiteResource($site->load('departements')),
        ]);
    }

    public function update(UpdateSiteRequest $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        DB::transaction(function () use ($request, $site) {
            $site->fill($request->safe()->except(['departement_ids']))->save();

            if ($request->has('departement_ids')) {
                $site->departements()->sync($request->input('departement_ids', []));
            }
        });

        return response()->json([
            'message' => 'Site mis à jour.',
            'site' => new SiteResource($site->fresh()->load('departements')),
        ]);
    }

    public function destroy(Site $site): JsonResponse
    {
        $this->authorize('delete', $site);

        if ($site->pointages()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer : des pointages sont liés à ce site. Désactivez-le plutôt.',
            ], 422);
        }

        $site->departements()->detach();
        $site->delete();

        return response()->json(['message' => 'Site supprimé.']);
    }
}
