<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Demandes\DecideDemandeRequest;
use App\Http\Requests\Demandes\StoreDemandeRequest;
use App\Http\Resources\DemandeResource;
use App\Models\AbsenceRequest;
use App\Services\DemandeService;
use App\Services\MediaService;
use App\Services\NotificationService;
use App\Services\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DemandeController extends Controller
{
    public function __construct(
        private readonly DemandeService $demandeService,
        private readonly NotificationService $notificationService,
        private readonly MediaService $mediaService,
        private readonly RealtimePublisher $realtime,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AbsenceRequest::class);

        $query = AbsenceRequest::query()
            ->with(['agent.departement', 'approbateur'])
            ->latest('id');

        if ($request->user()->isFieldUser()) {
            $query->where('agent_id', $request->user()->agent?->id);
        }

        if ($request->filled('type_demande')) {
            $query->where('type_demande', $request->string('type_demande'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->filled('agent_id') && ! $request->user()->isFieldUser()) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('motif', 'like', $q)
                    ->orWhereHas('agent', function ($a) use ($q) {
                        $a->where('matricule', 'like', $q)
                            ->orWhere('prenom', 'like', $q)
                            ->orWhere('nom', 'like', $q);
                    });
            });
        }

        return DemandeResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 15))))
        );
    }

    public function store(StoreDemandeRequest $request): JsonResponse
    {
        $this->authorize('create', AbsenceRequest::class);

        $actor = $request->user();
        $agentId = $this->resolveTargetAgentId($request, $actor);

        if (! $agentId) {
            return response()->json([
                'message' => 'agent_id requis.',
                'errors' => ['agent_id' => ['Agent obligatoire.']],
            ], 422);
        }

        // Sous-admin : uniquement sa propre fiche
        if ($actor->hasRole('sous_admin')
            && (int) $agentId !== (int) $actor->agent?->id) {
            return response()->json(['message' => 'Vous ne pouvez soumettre une demande que pour votre propre fiche.'], 403);
        }

        $documentPath = $request->input('document_path');
        if ($request->hasFile('document')) {
            $stored = $this->mediaService->store($request->file('document'), 'demande_document');
            $documentPath = $stored['path'];
        }

        $demande = DB::transaction(function () use ($request, $actor, $agentId, $documentPath) {
            $heureDebut = $request->input('heure_debut');
            $heureFin = $request->input('heure_fin');

            $demande = AbsenceRequest::query()->create([
                'agent_id' => $agentId,
                'type_demande' => $request->string('type_demande')->toString(),
                'date_debut' => $request->string('date_debut')->toString(),
                'date_fin' => $request->string('date_fin')->toString(),
                'heure_debut' => $heureDebut ? $heureDebut.':00' : null,
                'heure_fin' => $heureFin ? $heureFin.':00' : null,
                'motif' => $request->string('motif')->toString(),
                'extra_json' => $request->input('extra'),
                'document_path' => $documentPath,
                'statut' => 'EN_ATTENTE',
            ]);

            $this->demandeService->recordHistory(
                $demande,
                null,
                'EN_ATTENTE',
                $actor,
                'Soumission de la demande',
            );

            $type = $demande->type_demande;
            $recipients = $this->notificationService->recipientsForNewRequest($actor, (int) $agentId);
            $this->notificationService->notifyMany(
                $recipients,
                "Nouvelle demande {$type}",
                "Une demande {$type} vient d’être soumise.",
                'confirmation',
                strtolower($type),
                'AbsenceRequest',
                $demande->id,
                playSound: true,
            );

            return $demande->load(['agent.departement', 'history']);
        });

        $this->realtime->publishForAdminAndAgent('demande.created', [
            'resource' => 'demande',
            'id' => $demande->id,
            'agent_id' => $demande->agent_id,
            'type_demande' => $demande->type_demande,
            'statut' => $demande->statut,
        ], (int) $demande->agent_id);

        return response()->json([
            'message' => 'Demande créée.',
            'demande' => new DemandeResource($demande),
        ], 201);
    }

    public function show(Request $request, AbsenceRequest $demande): JsonResponse
    {
        $this->authorize('view', $demande);

        // Ouverture par un superviseur habilité → EN_COURS
        if ($request->user()->isAdminStaff() && $demande->statut === 'EN_ATTENTE') {
            $demande->loadMissing('agent.user.role');
            if ($this->notificationService->canDecideForOwner($request->user(), $demande->agent?->user)) {
                $this->demandeService->markAsEnCours($demande, $request->user());
                $demande->refresh();

                $this->realtime->publishForAdminAndAgent('demande.updated', [
                    'resource' => 'demande',
                    'id' => $demande->id,
                    'agent_id' => $demande->agent_id,
                    'type_demande' => $demande->type_demande,
                    'statut' => $demande->statut,
                ], (int) $demande->agent_id);
            }
        }

        $demande->load(['agent.departement', 'approbateur', 'history', 'lecteurAdmin']);

        return response()->json([
            'demande' => new DemandeResource($demande),
        ]);
    }

    public function decide(DecideDemandeRequest $request, AbsenceRequest $demande): JsonResponse
    {
        $this->authorize('decide', $demande);

        $updated = $this->demandeService->decide(
            $demande,
            $request->user(),
            $request->string('decision')->toString(),
            $request->input('motif_rejet'),
            $request->input('commentaire'),
        );

        $this->realtime->publishForAdminAndAgent('demande.updated', [
            'resource' => 'demande',
            'id' => $updated->id,
            'agent_id' => $updated->agent_id,
            'type_demande' => $updated->type_demande,
            'statut' => $updated->statut,
            'decision' => $request->string('decision')->toString(),
        ], (int) $updated->agent_id);

        return response()->json([
            'message' => 'Décision enregistrée.',
            'demande' => new DemandeResource($updated),
        ]);
    }

    public function cancel(Request $request, AbsenceRequest $demande): JsonResponse
    {
        $this->authorize('cancel', $demande);

        $updated = $this->demandeService->cancel($demande, $request->user());

        $this->realtime->publishForAdminAndAgent('demande.updated', [
            'resource' => 'demande',
            'id' => $updated->id,
            'agent_id' => $updated->agent_id,
            'type_demande' => $updated->type_demande,
            'statut' => $updated->statut,
            'decision' => 'ANNULEE',
        ], (int) $updated->agent_id);

        return response()->json([
            'message' => 'Demande annulée.',
            'demande' => new DemandeResource($updated->load(['agent.departement', 'history'])),
        ]);
    }

    public function destroy(AbsenceRequest $demande): JsonResponse
    {
        $this->authorize('delete', $demande);

        $payload = [
            'resource' => 'demande',
            'id' => $demande->id,
            'agent_id' => $demande->agent_id,
            'type_demande' => $demande->type_demande,
            'statut' => $demande->statut,
            'action' => 'delete',
        ];
        $agentId = (int) $demande->agent_id;

        if ($demande->document_path) {
            $this->mediaService->delete($demande->document_path);
        }

        $demande->delete();

        $this->realtime->publishForAdminAndAgent('demande.deleted', $payload, $agentId);

        return response()->json(['message' => 'Demande supprimée.']);
    }

    /**
     * Terrain / staff mobile sans agent_id → fiche liée.
     * Web admin → agent_id obligatoire (sauf si on force la fiche perso).
     */
    private function resolveTargetAgentId(Request $request, $actor): ?int
    {
        $tokenName = strtolower((string) $actor->currentAccessToken()?->name);
        $isMobile = str_contains($tokenName, 'mobile') || str_contains($tokenName, 'pointage_mobile');

        if ($actor->isFieldUser()) {
            return $actor->agent?->id;
        }

        if ($request->filled('agent_id')) {
            return $request->integer('agent_id');
        }

        if ($isMobile && $actor->agent) {
            return (int) $actor->agent->id;
        }

        return null;
    }
}
