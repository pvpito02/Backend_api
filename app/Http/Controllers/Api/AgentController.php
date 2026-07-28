<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\StoreAgentRequest;
use App\Http\Requests\Agents\UpdateAgentRequest;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use App\Services\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentController extends Controller
{
    public function __construct(private readonly RealtimePublisher $realtime) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Agent::class);

        $query = Agent::query()
            ->with(['departement', 'supervisor', 'user'])
            ->orderBy('nom')
            ->orderBy('prenom');

        if ($request->filled('departement_id')) {
            $query->where('departement_id', $request->integer('departement_id'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('without_user')) {
            $query->whereNull('user_id');
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('matricule', 'like', $q)
                    ->orWhere('prenom', 'like', $q)
                    ->orWhere('nom', 'like', $q)
                    ->orWhere('email', 'like', $q)
                    ->orWhere('poste', 'like', $q)
                    ->orWhere('telephone', 'like', $q);
            });
        }

        return AgentResource::collection(
            $query->paginate(min(500, max(1, (int) $request->input('per_page', 15))))
        );
    }

    public function store(StoreAgentRequest $request): JsonResponse
    {
        $this->authorize('create', Agent::class);

        $agent = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['create_user', 'password', 'password_confirmation']);
            $data['statut'] = $data['statut'] ?? 'Actif';
            $data['is_active'] = $data['is_active'] ?? true;

            if ($request->boolean('create_user')) {
                $roleId = Role::query()->where('name', 'agent')->value('id');
                $email = $data['email'] ?? null;

                if (! $email) {
                    throw ValidationException::withMessages([
                        'email' => ['Un email est requis pour créer le compte utilisateur.'],
                    ]);
                }

                if (User::query()->where('email', $email)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => ['Cet email est déjà utilisé par un compte utilisateur.'],
                    ]);
                }

                $user = User::query()->create([
                    'role_id' => $roleId,
                    'name' => trim(($data['prenom'] ?? '').' '.($data['nom'] ?? '')),
                    'email' => $email,
                    'phone' => $data['telephone'] ?? null,
                    'password' => $request->string('password')->toString(),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $data['user_id'] = $user->id;
            }

            return Agent::query()->create($data)->load(['departement', 'supervisor', 'user']);
        });

        $this->publishAgentEvent('agent.created', $agent);
        if ($agent->user_id) {
            $this->realtime->publish('user.created', [
                'resource' => 'user',
                'id' => $agent->user_id,
                'role' => 'agent',
                'is_active' => true,
                'agent_id' => $agent->id,
            ], 'admin', null);
        }

        return response()->json([
            'message' => 'Agent créé.',
            'agent' => new AgentResource($agent),
        ], 201);
    }

    public function show(Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $agent->load(['departement', 'supervisor', 'user']);

        return response()->json([
            'agent' => new AgentResource($agent),
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): JsonResponse
    {
        $this->authorize('update', $agent);

        $data = $request->validated();

        if (array_key_exists('statut', $data) && ! array_key_exists('is_active', $data)) {
            $data['is_active'] = $data['statut'] === 'Actif';
        }

        $agent->fill($data)->save();
        $agent->load(['departement', 'supervisor', 'user']);

        $this->publishAgentEvent('agent.updated', $agent);

        return response()->json([
            'message' => 'Agent mis à jour.',
            'agent' => new AgentResource($agent),
        ]);
    }

    public function destroy(Request $request, Agent $agent): JsonResponse
    {
        $this->authorize('delete', $agent);

        if ($agent->subordinates()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer : cet agent supervise d’autres agents.',
            ], 422);
        }

        $agentId = $agent->id;
        $userId = $agent->user_id;
        $deactivateUser = $request->boolean('deactivate_user');

        DB::transaction(function () use ($agent, $request, $deactivateUser) {
            $user = $agent->user;

            $agent->delete();

            if ($deactivateUser && $user) {
                $user->forceFill(['is_active' => false])->save();
                $user->tokens()->delete();
            }
        });

        $this->realtime->publishForAdminAndAgent('agent.deleted', [
            'resource' => 'agent',
            'id' => $agentId,
            'user_id' => $userId,
            'action' => 'delete',
        ], $agentId);

        if ($deactivateUser && $userId) {
            $this->realtime->publish('user.updated', [
                'resource' => 'user',
                'id' => $userId,
                'role' => 'agent',
                'is_active' => false,
                'agent_id' => null,
                'action' => 'agent_deleted',
            ], 'admin', null);
            $this->realtime->publish('user.updated', [
                'resource' => 'user',
                'id' => $userId,
                'role' => 'agent',
                'is_active' => false,
                'agent_id' => null,
                'action' => 'agent_deleted',
            ], 'user', (int) $userId);
        }

        return response()->json([
            'message' => 'Agent supprimé.',
        ]);
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
}
