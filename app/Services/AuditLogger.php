<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(
        string $action,
        ?Model $model = null,
        ?array $details = null,
        ?User $user = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $user?->id ?? Auth::id(),
            'action' => $action,
            'model_type' => $model ? $model::class : null,
            'model_id' => $model?->getKey(),
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
