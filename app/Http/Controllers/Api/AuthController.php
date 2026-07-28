<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Agent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim($request->string('login')->toString());
        $throttleKey = Str::lower($login).'|'.$request->ip();

        $this->ensureIsNotRateLimited($throttleKey);

        $user = $this->resolveUser($login);

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($throttleKey, $this->lockSeconds());

            throw ValidationException::withMessages([
                'login' => ['Identifiant et/ou mot de passe incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Ce compte est désactivé.'],
            ]);
        }

        if ($user->role && ! $user->role->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Le rôle associé à ce compte est désactivé.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ])->save();

        $this->audit->log('auth.login', $user, [
            'ip' => $request->ip(),
            'device_name' => $request->input('device_name'),
        ], $user);

        $deviceName = $request->string('device_name')->toString() ?: ($request->userAgent() ?: 'api-token');
        $newToken = $user->createToken($deviceName);
        // Marquer immédiatement le token comme actif (sinon online attend la 1re requête)
        $newToken->accessToken->forceFill(['last_used_at' => now()])->save();
        $token = $newToken->plainTextToken;

        $user->load(['role', 'agent.departement']);
        $user->loadCount([
            'tokens as active_sessions_count' => function ($q) {
                User::constrainActiveTokens($q);
            },
        ]);

        return response()->json([
            'message' => 'Connexion réussie.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role', 'agent.departement']);
        $user->loadCount([
            'tokens as active_sessions_count' => function ($q) {
                User::constrainActiveTokens($q);
            },
        ]);

        // Touche la session courante (activité)
        $request->user()->currentAccessToken()?->forceFill([
            'last_used_at' => now(),
        ])->save();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Heartbeat multi-postes : prolonge la session courante sans autre effet.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $user = $request->user();
        $user->loadCount([
            'tokens as active_sessions_count' => function ($q) {
                User::constrainActiveTokens($q);
            },
        ]);

        return response()->json([
            'ok' => true,
            'is_online' => $user->isOnline(),
            'sessions_count' => $user->activeSessionsCount(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->user()->currentAccessToken()?->delete();

        // last_logout_at seulement s’il ne reste plus de session active
        $remaining = $user->tokens();
        User::constrainActiveTokens($remaining);
        if ($remaining->count() === 0) {
            $user->forceFill([
                'last_logout_at' => now(),
            ])->save();
        }

        $this->audit->log('auth.logout', $user, null, $user);

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        $request->user()->forceFill([
            'last_logout_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Toutes les sessions ont été révoquées.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->string('password')->toString();
        $user->save();

        // Révoquer les autres tokens après changement MDP
        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()?->id)
            ->delete();

        return response()->json([
            'message' => 'Mot de passe mis à jour.',
        ]);
    }

    private function resolveUser(string $login): ?User
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::query()
                ->with(['role', 'agent'])
                ->where('email', $login)
                ->first();
        }

        $agent = Agent::query()
            ->with(['user.role', 'user.agent'])
            ->where('matricule', $login)
            ->first();

        return $agent?->user;
    }

    private function ensureIsNotRateLimited(string $key): void
    {
        $maxAttempts = $this->maxLoginAttempts();

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'login' => ["Trop de tentatives. Réessayez dans {$seconds} seconde(s)."],
        ]);
    }

    private function maxLoginAttempts(): int
    {
        try {
            $value = DB::table('remote_configs')
                ->where('key_name', 'max_login_attempts')
                ->where('is_active', 1)
                ->value('value_text');
        } catch (\Throwable) {
            $value = null;
        }

        $max = (int) ($value ?: 5);

        return max(3, $max);
    }

    private function lockSeconds(): int
    {
        try {
            $value = DB::table('remote_configs')
                ->where('key_name', 'lock_minutes')
                ->where('is_active', 1)
                ->value('value_text');
        } catch (\Throwable) {
            $value = null;
        }

        $minutes = (int) ($value ?: 15);

        return max(60, $minutes * 60);
    }
}
