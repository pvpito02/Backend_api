<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileFeatures\UpdateMobileFeatureRequest;
use App\Http\Resources\MobileFeatureResource;
use App\Models\MobileFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MobileFeatureController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MobileFeature::class);

        $query = MobileFeature::query()->orderBy('sort_order');

        if ($request->user()->hasRole('agent') || $request->boolean('visible_only')) {
            $query->where('is_visible', true);
        }

        return MobileFeatureResource::collection($query->get());
    }

    public function show(MobileFeature $mobileFeature): JsonResponse
    {
        $this->authorize('view', $mobileFeature);

        return response()->json([
            'feature' => new MobileFeatureResource($mobileFeature),
        ]);
    }

    public function update(UpdateMobileFeatureRequest $request, MobileFeature $mobileFeature): JsonResponse
    {
        $this->authorize('update', $mobileFeature);

        $data = $request->safe()->except(['visible']);
        $mobileFeature->fill($data)->save();

        return response()->json([
            'message' => 'Module mobile mis à jour.',
            'feature' => new MobileFeatureResource($mobileFeature),
        ]);
    }
}
