<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkSchedules\UpdateWorkScheduleRequest;
use App\Http\Resources\WorkScheduleResource;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkScheduleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('viewAny', WorkSchedule::class);

        if ($request->boolean('default')) {
            $schedule = WorkSchedule::activeDefault();

            return response()->json([
                'schedule' => $schedule ? new WorkScheduleResource($schedule) : null,
            ]);
        }

        return WorkScheduleResource::collection(
            WorkSchedule::query()->orderBy('name')->get()
        );
    }

    public function show(WorkSchedule $workSchedule): JsonResponse
    {
        $this->authorize('view', $workSchedule);

        return response()->json([
            'schedule' => new WorkScheduleResource($workSchedule),
        ]);
    }

    public function update(UpdateWorkScheduleRequest $request, WorkSchedule $workSchedule): JsonResponse
    {
        $this->authorize('update', $workSchedule);

        $data = $request->validated();
        foreach (['entry_time', 'exit_time', 'friday_exit_time'] as $timeField) {
            if (isset($data[$timeField]) && strlen($data[$timeField]) === 5) {
                $data[$timeField] .= ':00';
            }
        }

        $workSchedule->fill($data)->save();

        return response()->json([
            'message' => 'Horaires mis à jour.',
            'schedule' => new WorkScheduleResource($workSchedule),
        ]);
    }
}
