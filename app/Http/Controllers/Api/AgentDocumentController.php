<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentDocuments\StoreAgentDocumentRequest;
use App\Http\Requests\AgentDocuments\UpdateAgentDocumentRequest;
use App\Http\Resources\AgentDocumentResource;
use App\Models\Agent;
use App\Models\AgentDocument;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgentDocumentController extends Controller
{
    public const DOC_TYPES = ['PHOTO', 'CONTRAT', 'CNI', 'HISTORIQUE', 'AUTRE'];

    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AgentDocument::class);

        $query = AgentDocument::query()->with(['agent', 'uploader'])->latest('id');

        if ($request->user()->isFieldUser()) {
            $query->where('agent_id', $request->user()->agent?->id);
        }

        if ($request->filled('agent_id') && ! $request->user()->isFieldUser()) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        if ($request->filled('type_document')) {
            $query->where('type_document', $request->string('type_document'));
        }

        return AgentDocumentResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    /** Vue dossiers admin : checklist docs par agent. */
    public function dossiers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AgentDocument::class);

        if ($request->user()->isFieldUser()) {
            return response()->json(['message' => 'Accès réservé à l’administration.'], 403);
        }

        $types = ['PHOTO', 'CONTRAT', 'CNI', 'HISTORIQUE'];

        $agents = Agent::query()
            ->with(['departement', 'documents'])
            ->orderBy('nom')
            ->paginate(min(500, max(1, (int) $request->input('per_page', 15))));

        $data = $agents->getCollection()->map(function (Agent $agent) use ($types) {
            $docs = [];
            foreach ($types as $type) {
                $doc = $agent->documents->firstWhere('type_document', $type);
                $docs[strtolower($type)] = (bool) ($doc?->is_present && $doc?->file_path);
            }

            $photoDoc = $agent->documents->firstWhere('type_document', 'PHOTO');
            $photoSource = $agent->photo_url
                ?: (($photoDoc?->is_present && $photoDoc?->file_path) ? $photoDoc->file_path : null);

            // Profil agent (photo_url) compte aussi comme photo de dossier présente
            if ($agent->photo_url) {
                $docs['photo'] = true;
            }

            return [
                'id' => $agent->id,
                'matricule' => $agent->matricule,
                'name' => $agent->nom_complet,
                'role' => $agent->poste,
                'service' => $agent->departement?->nom,
                'email' => $agent->email,
                'telephone' => $agent->telephone,
                'photo' => MediaUrl::public($photoSource),
                'docs' => $docs,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $agents->currentPage(),
                'last_page' => $agents->lastPage(),
                'per_page' => $agents->perPage(),
                'total' => $agents->total(),
            ],
        ]);
    }

    public function store(StoreAgentDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', AgentDocument::class);

        $agentId = $request->user()->isFieldUser()
            ? $request->user()->agent?->id
            : $request->integer('agent_id');

        if (! $agentId) {
            return response()->json(['message' => 'agent_id requis.'], 422);
        }

        $filePath = $request->input('file_path');
        $original = null;
        $mime = null;

        if ($request->hasFile('file')) {
            $folder = $request->string('type_document')->toString() === 'PHOTO'
                ? 'agent_photo'
                : 'agent_document';
            $stored = $this->media->store($request->file('file'), $folder);
            $filePath = $stored['path'];
            $original = $stored['original_name'];
            $mime = $stored['mime'];
        }

        $typeDocument = $request->string('type_document')->toString();

        $doc = AgentDocument::query()->updateOrCreate(
            [
                'agent_id' => $agentId,
                'type_document' => $typeDocument,
            ],
            [
                'file_path' => $filePath,
                'original_name' => $original,
                'mime_type' => $mime,
                'is_present' => $request->input('is_present', (bool) $filePath),
                'uploaded_by' => $request->user()->id,
                'notes' => $request->input('notes'),
            ]
        )->load(['agent', 'uploader']);

        // PHOTO dossier → synchronise aussi la photo de profil de l’agent
        if ($typeDocument === 'PHOTO' && $filePath) {
            Agent::query()->whereKey($agentId)->update(['photo_url' => $filePath]);
            $doc->setRelation('agent', $doc->agent?->fresh() ?? Agent::query()->find($agentId));
        }

        $this->audit->log('agent_document.upsert', $doc, [
            'type' => $doc->type_document,
            'agent_id' => $agentId,
        ]);

        return response()->json([
            'message' => 'Document enregistré.',
            'document' => new AgentDocumentResource($doc),
        ], 201);
    }

    public function show(AgentDocument $agentDocument): JsonResponse
    {
        $this->authorize('view', $agentDocument);

        return response()->json([
            'document' => new AgentDocumentResource($agentDocument->load(['agent', 'uploader'])),
        ]);
    }

    public function update(UpdateAgentDocumentRequest $request, AgentDocument $agentDocument): JsonResponse
    {
        $this->authorize('update', $agentDocument);

        $data = $request->safe()->except(['file']);

        if ($request->hasFile('file')) {
            if ($agentDocument->file_path) {
                $this->media->delete($agentDocument->file_path);
            }
            $folder = $agentDocument->type_document === 'PHOTO' ? 'agent_photo' : 'agent_document';
            $stored = $this->media->store($request->file('file'), $folder);
            $data['file_path'] = $stored['path'];
            $data['original_name'] = $stored['original_name'];
            $data['mime_type'] = $stored['mime'];
            $data['is_present'] = true;
            $data['uploaded_by'] = $request->user()->id;
        }

        $agentDocument->fill($data)->save();

        if ($agentDocument->type_document === 'PHOTO' && $agentDocument->file_path) {
            $agentDocument->agent?->forceFill([
                'photo_url' => $agentDocument->file_path,
            ])->save();
        }

        $this->audit->log('agent_document.update', $agentDocument);

        return response()->json([
            'message' => 'Document mis à jour.',
            'document' => new AgentDocumentResource($agentDocument->fresh()->load(['agent', 'uploader'])),
        ]);
    }

    public function destroy(AgentDocument $agentDocument): JsonResponse
    {
        $this->authorize('delete', $agentDocument);

        if ($agentDocument->file_path) {
            $this->media->delete($agentDocument->file_path);
        }

        $this->audit->log('agent_document.delete', $agentDocument, [
            'type' => $agentDocument->type_document,
        ]);

        $agentDocument->delete();

        return response()->json(['message' => 'Document supprimé.']);
    }
}
