<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Holidays\StoreHolidayRequest;
use App\Http\Requests\Holidays\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HolidayController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Holiday::class);

        $query = Holiday::query()->orderBy('date_holiday');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('is_active', true);
        }

        if ($request->filled('year')) {
            $query->whereYear('date_holiday', $request->integer('year'));
        }

        if ($request->filled('from')) {
            $query->whereDate('date_holiday', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date_holiday', '<=', $request->string('to'));
        }

        if ($request->filled('type_holiday')) {
            $query->where('type_holiday', $request->string('type_holiday'));
        }

        if ($request->boolean('all')) {
            return HolidayResource::collection($query->get());
        }

        return HolidayResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 30))))
        );
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $this->authorize('create', Holiday::class);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $holiday = Holiday::query()->create($data);

        return response()->json([
            'message' => 'Jour non travaillé créé.',
            'holiday' => new HolidayResource($holiday),
        ], 201);
    }

    public function show(Holiday $holiday): JsonResponse
    {
        $this->authorize('view', $holiday);

        return response()->json(['holiday' => new HolidayResource($holiday)]);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        $this->authorize('update', $holiday);

        $holiday->fill($request->validated())->save();

        return response()->json([
            'message' => 'Jour non travaillé mis à jour.',
            'holiday' => new HolidayResource($holiday),
        ]);
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        $this->authorize('delete', $holiday);

        $holiday->delete();

        return response()->json(['message' => 'Jour non travaillé supprimé.']);
    }
}
