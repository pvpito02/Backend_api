<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuditLogger
{
    public const PERMISSION_CREATE = 'create';

    public const PERMISSION_UPDATE = 'update';

    public const PERMISSION_DELETE = 'delete';

    public const PERMISSION_READ = 'read';

    public const PERMISSION_DECIDE = 'decide';

    public const PERMISSION_EXPORT = 'export';

    public const PERMISSION_LOGIN = 'login';

    public const PERMISSION_OTHER = 'other';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'plain_token',
        'secret',
        'pin',
        'otp',
    ];

    public function log(
        string $action,
        ?Model $model = null,
        ?array $details = null,
        ?User $user = null,
        ?string $permission = null,
        ?string $summary = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'user_id' => $user?->id ?? Auth::id(),
            'action' => $action,
            'permission' => $permission ?? $this->inferPermission($action),
            'summary' => $summary,
            'model_type' => $model ? $model::class : null,
            'model_id' => $model?->getKey(),
            'details' => $details !== null ? $this->sanitize($details) : null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request
                ? mb_substr((string) $request->userAgent(), 0, 255)
                : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Journalise une mutation / export admin réussi (middleware).
     */
    public function logAdminRequest(Request $request, Response $response): ?AuditLog
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isAdminStaff()) {
            return null;
        }

        if (! $this->shouldLogRequest($request)) {
            return null;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 400) {
            return null;
        }

        [$action, $permission, $summary] = $this->describeRequest($request);

        return $this->log(
            $action,
            null,
            [
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'status' => $status,
                'route' => $request->route()?->getName(),
                'input' => $this->safeInput($request),
            ],
            $user,
            $permission,
            $summary,
            $request,
        );
    }

    private function shouldLogRequest(Request $request): bool
    {
        $path = strtolower($request->path());
        $relative = preg_replace('#^api/#', '', $path) ?? $path;
        $method = strtoupper($request->method());

        $skipPrefixes = [
            'realtime/',
            'device-tokens',
            'notifications',
            'audit-logs',
        ];
        foreach ($skipPrefixes as $prefix) {
            if ($relative === rtrim($prefix, '/') || str_starts_with($relative, $prefix)) {
                return false;
            }
        }

        $skipExact = ['auth/me', 'auth/heartbeat', 'auth/logout', 'auth/logout-all'];
        if (in_array($relative, $skipExact, true)) {
            return false;
        }

        // Lectures sensibles uniquement
        if ($method === 'GET') {
            return str_starts_with($relative, 'exports/');
        }

        // Pointages terrain (scan/sync) : pas du journal « admin bureau »
        if (str_starts_with($relative, 'pointages/scan') || str_starts_with($relative, 'pointages/sync')) {
            return false;
        }

        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function describeRequest(Request $request): array
    {
        $method = strtoupper($request->method());
        $path = preg_replace('#^api/#', '', strtolower($request->path())) ?? '';
        $segments = array_values(array_filter(explode('/', $path)));
        $resource = $segments[0] ?? 'api';
        $hasId = isset($segments[1]) && ctype_digit((string) $segments[1]);
        $actionSeg = $hasId ? ($segments[2] ?? null) : ($segments[1] ?? null);

        if (str_starts_with($path, 'exports/')) {
            $kind = $segments[1] ?? 'data';

            return ["export.{$kind}", self::PERMISSION_EXPORT, "Export CSV · {$kind}"];
        }

        if ($actionSeg === 'decide' || str_ends_with($path, '/decide')) {
            return ["{$resource}.decide", self::PERMISSION_DECIDE, "Décision · {$resource}"];
        }

        if ($actionSeg === 'cancel') {
            return ["{$resource}.cancel", self::PERMISSION_UPDATE, "Annulation · {$resource}"];
        }

        if ($actionSeg === 'reset-password' || str_ends_with($path, '/reset-password')) {
            return ['user.reset_password', self::PERMISSION_UPDATE, 'Réinitialisation mot de passe'];
        }

        if ($actionSeg === 'bulk' || str_contains($path, 'remote-configs/bulk')) {
            return ['remote_config.bulk_update', self::PERMISSION_UPDATE, 'Paramètres · mise à jour groupée'];
        }

        if ($method === 'POST' && ! $hasId) {
            return ["{$resource}.create", self::PERMISSION_CREATE, "Création · {$resource}"];
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            return ["{$resource}.update", self::PERMISSION_UPDATE, "Modification · {$resource}"];
        }

        if ($method === 'DELETE') {
            return ["{$resource}.delete", self::PERMISSION_DELETE, "Suppression · {$resource}"];
        }

        if ($method === 'POST') {
            $label = $actionSeg ? "{$resource}.{$actionSeg}" : "{$resource}.action";

            return [$label, self::PERMISSION_OTHER, "Action · {$resource}".($actionSeg ? " · {$actionSeg}" : '')];
        }

        return ["{$resource}.read", self::PERMISSION_READ, "Lecture · {$resource}"];
    }

    private function inferPermission(string $action): string
    {
        $a = strtolower($action);
        if (str_contains($a, 'create') || str_contains($a, 'store') || str_contains($a, 'upsert')) {
            return self::PERMISSION_CREATE;
        }
        if (str_contains($a, 'delete') || str_contains($a, 'destroy')) {
            return self::PERMISSION_DELETE;
        }
        if (str_contains($a, 'decide') || str_contains($a, 'approve') || str_contains($a, 'reject')) {
            return self::PERMISSION_DECIDE;
        }
        if (str_contains($a, 'export')) {
            return self::PERMISSION_EXPORT;
        }
        if (str_contains($a, 'login')) {
            return self::PERMISSION_LOGIN;
        }
        if (str_contains($a, 'update') || str_contains($a, 'reset') || str_contains($a, 'cancel')) {
            return self::PERMISSION_UPDATE;
        }

        return self::PERMISSION_OTHER;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeInput(Request $request): ?array
    {
        $data = $request->except([
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'document',
            'file',
            'photo',
            'image',
        ]);

        if ($data === []) {
            return null;
        }

        // Limiter la taille (confidentialité + volume)
        $encoded = json_encode($this->sanitize($data));
        if ($encoded !== false && strlen($encoded) > 2000) {
            return ['_truncated' => true, 'keys' => array_keys($data)];
        }

        return $this->sanitize($data);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function sanitize(array $details): array
    {
        $out = [];
        foreach ($details as $key => $value) {
            $k = is_string($key) ? strtolower($key) : (string) $key;
            if (in_array($k, self::SENSITIVE_KEYS, true) || str_contains($k, 'password')) {
                $out[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->sanitize($value);
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
