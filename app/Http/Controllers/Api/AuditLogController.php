<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()
            ->with(['user.role'])
            ->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->string('action').'%');
        }

        if ($request->filled('permission')) {
            $query->where('permission', $request->string('permission'));
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->string('model_type'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to'));
        }

        // Acteurs staff admin uniquement (pas le bruit agents terrain)
        $query->whereHas(
            'user.role',
            fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'sous_admin', 'rh', 'direction'])
        );

        // Par défaut : activités des autres (RH…), pas le Super lui-même
        if ($request->boolean('others_only', true)) {
            $query->where('user_id', '!=', $request->user()->id)
                ->whereHas('user.role', fn ($q) => $q->where('name', '!=', 'super_admin'));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('summary', 'like', $q)
                    ->orWhere('action', 'like', $q)
                    ->orWhere('ip_address', 'like', $q)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $q)->orWhere('email', 'like', $q));
            });
        }

        return AuditLogResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 30))))
        );
    }

    public function show(AuditLog $auditLog): AuditLogResource
    {
        $this->authorize('view', $auditLog);

        return new AuditLogResource($auditLog->load(['user.role']));
    }
}
