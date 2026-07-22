<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QrCodes\StoreQrCodeRequest;
use App\Http\Requests\QrCodes\UpdateQrCodeRequest;
use App\Http\Resources\QrCodeResource;
use App\Models\Agent;
use App\Models\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', QrCode::class);

        $query = QrCode::query()->with('agent')->latest('id');

        if ($request->user()->hasRole('agent')) {
            $query->where('agent_id', $request->user()->agent?->id);
        }

        if ($request->filled('agent_id') && ! $request->user()->hasRole('agent')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        $items = $query->paginate(min(100, max(1, (int) $request->input('per_page', 20))));
        $items->getCollection()->each->refreshExpiredStatus();

        return QrCodeResource::collection($items);
    }

    public function store(StoreQrCodeRequest $request): JsonResponse
    {
        $this->authorize('create', QrCode::class);

        $agent = Agent::query()->findOrFail($request->integer('agent_id'));

        $qr = DB::transaction(function () use ($request, $agent) {
            if ($request->boolean('revoke_previous', true)) {
                QrCode::query()
                    ->where('agent_id', $agent->id)
                    ->where('statut', 'ACTIF')
                    ->update(['statut' => 'REVOQUE']);
            }

            $code = $request->input('code')
                ?: sprintf('SANDIARA:%s:%s', $agent->matricule, Str::upper(Str::random(8)));

            return QrCode::query()->create([
                'agent_id' => $agent->id,
                'code' => $code,
                'issued_at' => now(),
                'expires_at' => $request->input('expires_at'),
                'statut' => 'ACTIF',
            ])->load('agent');
        });

        return response()->json([
            'message' => 'QR agent généré.',
            'qr_code' => new QrCodeResource($qr),
        ], 201);
    }

    public function show(QrCode $qrCode): JsonResponse
    {
        $this->authorize('view', $qrCode);

        $qrCode->refreshExpiredStatus();

        return response()->json([
            'qr_code' => new QrCodeResource($qrCode->load('agent')),
        ]);
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode): JsonResponse
    {
        $this->authorize('update', $qrCode);

        $qrCode->fill($request->validated())->save();

        return response()->json([
            'message' => 'QR mis à jour.',
            'qr_code' => new QrCodeResource($qrCode->fresh()->load('agent')),
        ]);
    }

    public function revoke(QrCode $qrCode): JsonResponse
    {
        $this->authorize('revoke', $qrCode);

        $qrCode->update(['statut' => 'REVOQUE']);

        return response()->json([
            'message' => 'QR révoqué.',
            'qr_code' => new QrCodeResource($qrCode->fresh()->load('agent')),
        ]);
    }

    public function destroy(QrCode $qrCode): JsonResponse
    {
        $this->authorize('delete', $qrCode);

        $qrCode->delete();

        return response()->json(['message' => 'QR supprimé.']);
    }

    /** QR actif de l’agent connecté (mobile). */
    public function mine(Request $request): JsonResponse
    {
        $agent = $request->user()->agent;
        if (! $agent) {
            return response()->json(['message' => 'Aucun profil agent.'], 422);
        }

        $qr = QrCode::query()
            ->where('agent_id', $agent->id)
            ->where('statut', 'ACTIF')
            ->latest('id')
            ->first();

        $qr?->refreshExpiredStatus();
        $qr?->refresh();

        if (! $qr || $qr->statut !== 'ACTIF') {
            return response()->json(['qr_code' => null, 'message' => 'Aucun QR actif.']);
        }

        $this->authorize('view', $qr);

        return response()->json([
            'qr_code' => new QrCodeResource($qr->load('agent')),
        ]);
    }
}
