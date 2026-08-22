<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /** Liste des rôles actifs (pour formulaires admin). */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        // Super : catalogue de gestion (tous + compteurs)
        if ($request->user()->isSuperAdmin() && $request->boolean('manage')) {
            $this->authorize('viewAny', Role::class);

            $query = Role::query()->withCount('users')->orderBy('id');

            if ($request->filled('q')) {
                $q = '%'.$request->string('q').'%';
                $query->where(function ($builder) use ($q) {
                    $builder->where('name', 'like', $q)
                        ->orWhere('display_name', 'like', $q)
                        ->orWhere('description', 'like', $q);
                });
            }

            return RoleResource::collection($query->get());
        }

        $roles = Role::query()
            ->where('is_active', true)
            ->when(
                ! $request->user()?->isSuperAdmin(),
                fn ($q) => $q->whereIn('name', $request->user()?->assignableRoleNames() ?? []),
            )
            ->orderBy('id')
            ->get(['id', 'name', 'display_name', 'description']);

        return response()->json(['data' => $roles]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        // Interdire de recréer un slug système sous un autre libellé via typo
        if (in_array($data['name'], Role::SYSTEM_NAMES, true)) {
            throw ValidationException::withMessages([
                'name' => ['Ce code est réservé à un rôle système.'],
            ]);
        }

        $role = Role::query()->create($data);
        $role->loadCount('users');

        return response()->json([
            'message' => 'Rôle créé.',
            'role' => new RoleResource($role),
        ], 201);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $role->loadCount('users');

        return response()->json([
            'role' => new RoleResource($role),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $data = $request->validated();

        if ($role->isSystem() && array_key_exists('name', $data) && $data['name'] !== $role->name) {
            throw ValidationException::withMessages([
                'name' => ['Le code d’un rôle système ne peut pas être modifié.'],
            ]);
        }

        // Ne pas désactiver Super s’il reste le seul compte Super actif
        if (
            $role->name === 'super_admin'
            && array_key_exists('is_active', $data)
            && ! $data['is_active']
        ) {
            throw ValidationException::withMessages([
                'is_active' => ['Le rôle Super administrateur ne peut pas être désactivé.'],
            ]);
        }

        $role->update($data);
        $role->loadCount('users');

        return response()->json([
            'message' => 'Rôle mis à jour.',
            'role' => new RoleResource($role),
        ]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if ($role->isSystem()) {
            throw ValidationException::withMessages([
                'role' => ['Un rôle système ne peut pas être supprimé.'],
            ]);
        }

        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            throw ValidationException::withMessages([
                'role' => ["Impossible de supprimer : {$usersCount} utilisateur(s) sont encore liés à ce rôle."],
            ]);
        }

        $role->delete();

        return response()->json([
            'message' => 'Rôle supprimé.',
        ]);
    }
}
