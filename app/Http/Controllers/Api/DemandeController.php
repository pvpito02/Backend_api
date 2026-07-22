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
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AbsenceRequest::class);

        $query = AbsenceRequest::query()
            ->with(['agent.departement', 'approbateur'])
            ->latest('id');

        if ($request->user()->hasRole('agent')) {
            $query->where('agent_id', $request->user()->agent?->id);
        }

        if ($request->filled('type_demande')) {
            $query->where('type_demande', $request->string('type_demande'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->filled('agent_id') && ! $request->user()->hasRole('agent')) {
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

        $agentId = $request->user()->hasRole('agent')
            ? $request->user()->agent?->id
            : $request->integer('agent_id');

        if (! $agentId) {
            return response()->json([
                'message' => 'agent_id requis.',
                'errors' => ['agent_id' => ['Agent obligatoire.']],
            ], 422);
        }

        $documentPath = $request->input('document_path');
        if ($request->hasFile('document')) {
            $stored = $this->mediaService->store($request->file('document'), 'demande_document');
            $documentPath = $stored['path'];
        }

        $demande = DB::transaction(function () use ($request, $agentId, $documentPath) {
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
                $request->user(),
                'Soumission de la demande',
            );

            $type = $demande->type_demande;
            $this->notificationService->notifyMany(
                $this->notificationService->adminStaffUsers(),
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

        return response()->json([
            'message' => 'Demande créée.',
            'demande' => new DemandeResource($demande),
        ], 201);
    }

    public function show(Request $request, AbsenceRequest $demande): JsonResponse
    {
        $this->authorize('view', $demande);

        // Ouverture admin → EN_COURS
        if ($request->user()->isAdminStaff() && $demande->statut === 'EN_ATTENTE') {
            $this->demandeService->markAsEnCours($demande, $request->user());
            $demande->refresh();
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

        return response()->json([
            'message' => 'Décision enregistrée.',
            'demande' => new DemandeResource($updated),
        ]);
    }

    public function cancel(Request $request, AbsenceRequest $demande): JsonResponse
    {
        $this->authorize('cancel', $demande);

        $updated = $this->demandeService->cancel($demande, $request->user());

        return response()->json([
            'message' => 'Demande annulée.',
            'demande' => new DemandeResource($updated->load(['agent.departement', 'history'])),
        ]);
    }

    public function destroy(AbsenceRequest $demande): JsonResponse
    {
        $this->authorize('delete', $demande);

        if ($demande->document_path) {
            $this->mediaService->delete($demande->document_path);
        }

        $demande->delete();

        return response()->json(['message' => 'Demande supprimée.']);
    }
}
