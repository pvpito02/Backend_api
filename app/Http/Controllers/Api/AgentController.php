<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\StoreAgentRequest;
use App\Http\Requests\Agents\UpdateAgentRequest;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentController extends Controller
{
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
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 15))))
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

        DB::transaction(function () use ($agent, $request) {
            $user = $agent->user;

            $agent->delete();

            if ($request->boolean('deactivate_user') && $user) {
                $user->forceFill(['is_active' => false])->save();
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'message' => 'Agent supprimé.',
        ]);
    }
}
