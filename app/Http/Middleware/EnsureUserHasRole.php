<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  string  ...$roles  Noms de rôles autorisés (ex: super_admin, admin)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_active) {
            return response()->json([
                'message' => 'Non authentifié ou compte inactif.',
            ], 401);
        }

        if ($roles !== [] && ! $user->hasRole($roles)) {
            return response()->json([
                'message' => 'Accès refusé pour ce rôle.',
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}
