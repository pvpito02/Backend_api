<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim($request->string('login')->toString());
        $throttleKey = Str::lower($login).'|'.$request->ip();

        $this->ensureIsNotRateLimited($throttleKey);

        $user = $this->resolveUser($login);

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($throttleKey, $this->lockSeconds());

            throw ValidationException::withMessages([
                'login' => ['Identifiants incorrects.'],
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

        $deviceName = $request->string('device_name')->toString() ?: ($request->userAgent() ?: 'api-token');
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load(['role', 'agent']);

        return response()->json([
            'message' => 'Connexion réussie.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role', 'agent']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'last_logout_at' => now(),
        ])->save();

        $request->user()->currentAccessToken()?->delete();

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
        $value = DB::table('remote_configs')
            ->where('key_name', 'max_login_attempts')
            ->where('is_active', 1)
            ->value('value_text');

        $max = (int) ($value ?: 5);

        return max(3, $max);
    }

    private function lockSeconds(): int
    {
        $value = DB::table('remote_configs')
            ->where('key_name', 'lock_minutes')
            ->where('is_active', 1)
            ->value('value_text');

        $minutes = (int) ($value ?: 15);

        return max(1, $minutes) * 60;
    }
}
