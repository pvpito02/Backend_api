<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceTokens\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get(['id', 'platform', 'device_name', 'is_active', 'last_used_at', 'created_at']);

        return response()->json(['data' => $tokens]);
    }

    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $token = DeviceToken::query()->updateOrCreate(
            ['token' => $request->string('token')->toString()],
            [
                'user_id' => $request->user()->id,
                'platform' => $request->string('platform')->toString(),
                'device_name' => $request->input('device_name'),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Token appareil enregistré.',
            'device_token' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'device_name' => $token->device_name,
                'is_active' => $token->is_active,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $deleted = DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $token)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Token introuvable.'], 404);
        }

        return response()->json(['message' => 'Token désenregistré.']);
    }
}
