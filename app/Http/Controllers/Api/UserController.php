<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use App\Services\MatriculeGenerator;
use App\Services\RealtimePublisher;
use App\Services\StaffPointageProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private readonly RealtimePublisher $realtime,
        private readonly MatriculeGenerator $matricules,
        private readonly StaffPointageProfileService $staffProfiles,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['role', 'agent.departement'])->latest('id');

        // RH / sous-admin : masquer les comptes Super (gouvernance)
        if (! $request->user()?->isSuperAdmin()) {
            $query->whereHas('role', fn ($q) => $q->where('name', '!=', 'super_admin'));
        }

        $query->withCount([
            'tokens as active_sessions_count' => function ($q) {
                User::constrainActiveTokens($q);
            },
        ])->withMax('tokens as tokens_max_last_used_at', 'last_used_at');

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

            $roleName = Role::query()->whereKey($data['role_id'])->value('name');
            $data['permissions'] = $this->resolvePermissionsPayload($request, $roleName);

            $user = User::query()->create($data);

            if ($roleName === 'agent') {
                $agentId = $request->integer('agent_id');
                $agent = Agent::query()->whereKey($agentId)->lockForUpdate()->first();

                if (! $agent) {
                    throw ValidationException::withMessages([
                        'agent_id' => ['Agent introuvable.'],
                    ]);
                }

                if ($agent->user_id) {
                    throw ValidationException::withMessages([
                        'agent_id' => ['Cet agent a déjà un compte utilisateur.'],
                    ]);
                }

                $names = preg_split('/\s+/', trim((string) $request->input('name', '')), 2) ?: [];
                $prenom = $request->filled('prenom')
                    ? $request->string('prenom')->toString()
                    : ($names[0] ?? $agent->prenom);
                $nom = $request->filled('nom')
                    ? $request->string('nom')->toString()
                    : ($names[1] ?? $agent->nom);

                $agent->fill([
                    'prenom' => $prenom ?: $agent->prenom,
                    'nom' => $nom ?: $agent->nom,
                    'poste' => $request->exists('poste') ? $request->input('poste') : $agent->poste,
                    'departement_id' => $request->filled('departement_id')
                        ? $request->integer('departement_id')
                        : $agent->departement_id,
                    'email' => $user->email,
                    'telephone' => $user->phone ?: $agent->telephone,
                    'photo_url' => $request->filled('avatar_url')
                        ? $request->input('avatar_url')
                        : $agent->photo_url,
                    'statut' => $agent->statut ?: 'Actif',
                    'is_active' => true,
                ]);

                $agent->user_id = $user->id;
                $agent->save();
            } elseif (StaffPointageProfileService::isStaffRole($roleName)) {
                $names = preg_split('/\s+/', trim((string) $request->input('name', '')), 2) ?: [];
                $this->staffProfiles->ensureFor($user, [
                    'prenom' => $request->filled('prenom')
                        ? $request->string('prenom')->toString()
                        : ($names[0] ?? null),
                    'nom' => $request->filled('nom')
                        ? $request->string('nom')->toString()
                        : ($names[1] ?? null),
                    'poste' => $request->input('poste'),
                    'departement_id' => $request->filled('departement_id')
                        ? $request->integer('departement_id')
                        : null,
                ]);
            }

            return $user->load(['role', 'agent.departement', 'agent.qrCodes']);
        });

        $this->publishUserEvent('user.created', $user);
        if ($user->agent) {
            $this->publishAgentEvent('agent.created', $user->agent);
        }

        return response()->json([
            'message' => 'Utilisateur créé.',
            'user' => new UserResource($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['role', 'agent.departement']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = DB::transaction(function () use ($request, $user) {
            $data = $request->safe()->only([
                'name', 'email', 'phone', 'role_id', 'avatar_url', 'is_active',
            ]);

            if ($request->filled('password')) {
                $data['password'] = $request->string('password')->toString();
            }

            $roleId = $data['role_id'] ?? $user->role_id;
            $roleName = Role::query()->whereKey($roleId)->value('name');

            if ($request->user()?->isSuperAdmin() && ($request->exists('permissions') || isset($data['role_id']))) {
                $data['permissions'] = $this->resolvePermissionsPayload($request, $roleName, $user);
            } elseif (isset($data['role_id'])) {
                $isGranular = in_array($roleName, ['admin', 'rh'], true)
                    || ($roleName !== null && ! in_array($roleName, Role::SYSTEM_NAMES, true));
                if (! $isGranular) {
                    $data['permissions'] = null;
                }
            }

            $user->fill($data)->save();

            $roleName = Role::query()->whereKey($user->role_id)->value('name');

            if ($roleName === 'agent') {
                $agentPayload = [
                    'email' => $user->email,
                    'telephone' => $user->phone,
                ];

                if ($request->filled('prenom')) {
                    $agentPayload['prenom'] = $request->string('prenom')->toString();
                }
                if ($request->filled('nom')) {
                    $agentPayload['nom'] = $request->string('nom')->toString();
                }
                if ($request->exists('poste')) {
                    $agentPayload['poste'] = $request->input('poste');
                }
                if ($request->exists('departement_id')) {
                    $agentPayload['departement_id'] = $request->input('departement_id');
                }
                if ($request->filled('avatar_url')) {
                    $agentPayload['photo_url'] = $request->input('avatar_url');
                }

                $agent = $user->agent;
                if ($agent) {
                    $agent->fill($agentPayload)->save();
                } else {
                    $deptId = isset($agentPayload['departement_id'])
                        ? (int) $agentPayload['departement_id']
                        : null;
                    Agent::query()->create(array_merge([
                        'user_id' => $user->id,
                        'matricule' => $this->matricules->generate($deptId),
                        'prenom' => $request->string('prenom')->toString() ?: explode(' ', $user->name)[0],
                        'nom' => $request->string('nom')->toString() ?: $user->name,
                        'statut' => 'Actif',
                        'is_active' => true,
                        'photo_url' => $request->input('avatar_url'),
                    ], $agentPayload));
                }
            } elseif (StaffPointageProfileService::isStaffRole($roleName)) {
                $names = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];
                $this->staffProfiles->ensureFor($user->fresh(['role', 'agent']), [
                    'prenom' => $request->filled('prenom')
                        ? $request->string('prenom')->toString()
                        : ($names[0] ?? null),
                    'nom' => $request->filled('nom')
                        ? $request->string('nom')->toString()
                        : ($names[1] ?? null),
                    'poste' => $request->input('poste'),
                    'departement_id' => $request->exists('departement_id')
                        ? $request->input('departement_id')
                        : ($user->agent?->departement_id),
                ]);
            } elseif ($request->filled('avatar_url') && $user->agent) {
                $user->agent->forceFill(['photo_url' => $request->input('avatar_url')])->save();
            }

            return $user->load(['role', 'agent.departement', 'agent.qrCodes']);
        });

        $this->publishUserEvent('user.updated', $user);
        if ($user->agent) {
            $this->publishAgentEvent('agent.updated', $user->agent);
        }

        return response()->json([
            'message' => 'Utilisateur mis à jour.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Réinitialisation MDP par un admin (oubli / perte) — révoque les sessions.
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->forceFill([
            'password' => $request->string('password')->toString(),
        ])->save();

        $user->tokens()->delete();

        $user->load(['role', 'agent.departement']);
        $this->publishUserEvent('user.updated', $user, ['action' => 'password_reset']);

        return response()->json([
            'message' => 'Mot de passe réinitialisé. L’utilisateur devra se reconnecter.',
            'user' => new UserResource($user),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->load(['role', 'agent']);
        $userId = $user->id;
        $agent = $user->agent;
        $agentId = $agent?->id;
        $roleName = $user->role?->name;

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            // Ne pas supprimer l’agent (historique pointages) : désactiver + détacher.
            if ($user->agent) {
                $user->agent->forceFill([
                    'is_active' => false,
                    'statut' => 'Inactif',
                ])->save();
            }
            $user->delete();
        });

        $this->realtime->publish('user.deleted', [
            'resource' => 'user',
            'id' => $userId,
            'role' => $roleName,
            'agent_id' => $agentId,
        ], 'admin', null);

        if ($agentId) {
            $this->realtime->publishForAdminAndAgent('agent.updated', [
                'resource' => 'agent',
                'id' => $agentId,
                'user_id' => null,
                'is_active' => false,
                'statut' => 'Inactif',
                'action' => 'user_deleted',
            ], (int) $agentId);
        }

        return response()->json([
            'message' => 'Utilisateur supprimé.',
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function publishUserEvent(string $type, User $user, array $extra = []): void
    {
        $payload = array_merge([
            'resource' => 'user',
            'id' => $user->id,
            'role' => $user->role?->name
                ?? Role::query()->whereKey($user->role_id)->value('name'),
            'is_active' => (bool) $user->is_active,
            'agent_id' => $user->agent?->id,
        ], $extra);

        $this->realtime->publish($type, $payload, 'admin', null);
        $this->realtime->publish($type, $payload, 'user', (int) $user->id);
    }

    /** @param  array<string, mixed>  $extra */
    private function publishAgentEvent(string $type, Agent $agent, array $extra = []): void
    {
        $this->realtime->publishForAdminAndAgent($type, array_merge([
            'resource' => 'agent',
            'id' => $agent->id,
            'user_id' => $agent->user_id,
            'is_active' => (bool) $agent->is_active,
            'statut' => $agent->statut,
            'departement_id' => $agent->departement_id,
        ], $extra), (int) $agent->id);
    }

    /**
     * @return list<string>|null
     */
    private function resolvePermissionsPayload(Request $request, ?string $roleName, ?User $existing = null): ?array
    {
        $isGranular = in_array($roleName, ['admin', 'rh'], true)
            || ($roleName !== null && ! in_array($roleName, Role::SYSTEM_NAMES, true));

        if (! $isGranular) {
            return null;
        }

        $actor = $request->user();
        if ($actor?->isSuperAdmin() && $request->exists('permissions')) {
            return \App\Support\StaffPermissions::sanitize($request->input('permissions'));
        }

        if ($existing && is_array($existing->permissions) && $existing->permissions !== []) {
            return \App\Support\StaffPermissions::sanitize($existing->permissions);
        }

        return \App\Support\StaffPermissions::defaults();
    }
}
