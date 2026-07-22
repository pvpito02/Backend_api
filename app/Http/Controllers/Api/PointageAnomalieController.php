<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PointageAnomalieResource;
use App\Models\PointageAnomalie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PointageAnomalieController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PointageAnomalie::class);

        $query = PointageAnomalie::query()
            ->with(['pointage.agent', 'pointage.site'])
            ->latest('id');

        if ($request->has('resolved')) {
            $query->where('resolved', filter_var($request->input('resolved'), FILTER_VALIDATE_BOOLEAN));
        }

        return PointageAnomalieResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function resolve(PointageAnomalie $pointageAnomalie): JsonResponse
    {
        $this->authorize('resolve', $pointageAnomalie);

        $pointageAnomalie->forceFill([
            'resolved' => true,
            'resolved_by' => request()->user()->id,
            'resolved_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Anomalie résolue.',
            'anomalie' => new PointageAnomalieResource($pointageAnomalie),
        ]);
    }
}
