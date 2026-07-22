<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RemoteConfigs\BulkUpdateRemoteConfigRequest;
use App\Http\Requests\RemoteConfigs\UpsertRemoteConfigRequest;
use App\Http\Resources\RemoteConfigResource;
use App\Models\RemoteConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class RemoteConfigController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('viewAny', RemoteConfig::class);

        $query = RemoteConfig::query()->orderBy('key_name');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('as_map')) {
            $map = [];
            foreach ($query->where('is_active', true)->get() as $row) {
                $map[$row->key_name] = (new RemoteConfigResource($row))->resolve()['value'];
            }

            return response()->json(['configs' => $map]);
        }

        return RemoteConfigResource::collection($query->get());
    }

    public function store(UpsertRemoteConfigRequest $request): JsonResponse
    {
        $this->authorize('create', RemoteConfig::class);

        $config = RemoteConfig::query()->updateOrCreate(
            ['key_name' => $request->string('key_name')->toString()],
            [
                'value_text' => $request->input('value_text'),
                'description' => $request->input('description'),
                'is_active' => $request->input('is_active', true),
            ]
        );

        return response()->json([
            'message' => 'Configuration enregistrée.',
            'config' => new RemoteConfigResource($config),
        ], 201);
    }

    public function show(RemoteConfig $remoteConfig): JsonResponse
    {
        $this->authorize('view', $remoteConfig);

        return response()->json([
            'config' => new RemoteConfigResource($remoteConfig),
        ]);
    }

    public function update(UpsertRemoteConfigRequest $request, RemoteConfig $remoteConfig): JsonResponse
    {
        $this->authorize('update', $remoteConfig);

        $remoteConfig->fill($request->validated())->save();

        return response()->json([
            'message' => 'Configuration mise à jour.',
            'config' => new RemoteConfigResource($remoteConfig),
        ]);
    }

    public function bulkUpdate(BulkUpdateRemoteConfigRequest $request): JsonResponse
    {
        $this->authorize('update', RemoteConfig::class);

        DB::transaction(function () use ($request) {
            foreach ($request->input('configs', []) as $item) {
                RemoteConfig::query()->updateOrCreate(
                    ['key_name' => $item['key_name']],
                    [
                        'value_text' => $item['value_text'] ?? null,
                        'is_active' => $item['is_active'] ?? true,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Configurations mises à jour.',
            'configs' => RemoteConfigResource::collection(
                RemoteConfig::query()->orderBy('key_name')->get()
            ),
        ]);
    }

    public function destroy(RemoteConfig $remoteConfig): JsonResponse
    {
        $this->authorize('delete', $remoteConfig);

        $remoteConfig->delete();

        return response()->json(['message' => 'Clé de configuration supprimée.']);
    }
}
