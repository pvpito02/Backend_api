<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['role', 'agent'])->latest('id');

        if ($request->filled('role')) {
            $query->whereHas('role', fn ($q) => $q->where('name', $request->string('role')));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', $q)
                    ->orWhere('email', 'like', $q)
                    ->orWhere('phone', 'like', $q);
            });
        }

        return UserResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 15))))
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = DB::transaction(function () use ($request) {
            $data = $request->safe()->only([
                'name', 'email', 'phone', 'password', 'role_id', 'avatar_url', 'is_active',
            ]);

            $data['is_active'] = $data['is_active'] ?? true;

            $user = User::query()->create($data);

            $roleName = Role::query()->whereKey($user->role_id)->value('name');

            if ($roleName === 'agent') {
                Agent::query()->create([
                    'user_id' => $user->id,
                    'matricule' => $request->string('matricule')->toString(),
                    'prenom' => $request->string('prenom')->toString(),
                    'nom' => $request->string('nom')->toString(),
                    'poste' => $request->input('poste'),
                    'departement_id' => $request->input('departement_id'),
                    'email' => $user->email,
                    'telephone' => $user->phone,
                    'statut' => 'Actif',
                    'is_active' => true,
                ]);
            }

            return $user->load(['role', 'agent']);
        });

        return response()->json([
            'message' => 'Utilisateur créé.',
            'user' => new UserResource($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['role', 'agent']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->safe()->only([
            'name', 'email', 'phone', 'role_id', 'avatar_url', 'is_active',
        ]);

        if ($request->filled('password')) {
            $data['password'] = $request->string('password')->toString();
        }

        $user->fill($data)->save();
        $user->load(['role', 'agent']);

        return response()->json([
            'message' => 'Utilisateur mis à jour.',
            'user' => new UserResource($user),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Utilisateur supprimé.',
        ]);
    }
}
