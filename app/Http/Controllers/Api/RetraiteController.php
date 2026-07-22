<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retraites\StoreRetraiteRequest;
use App\Http\Requests\Retraites\UpdateRetraiteRequest;
use App\Http\Resources\RetraiteResource;
use App\Models\Agent;
use App\Models\RemoteConfig;
use App\Models\Retraite;
use App\Support\MediaUrl;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RetraiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Retraite::class);

        $query = Retraite::query()->with(['agent.departement', 'creator'])->latest('date_depart');

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        return RetraiteResource::collection(
            $query->paginate(min(100, max(1, (int) $request->input('per_page', 15))))
        );
    }

    /**
     * Alertes retraite calculées (config remote : âge min/limite + seuils mois).
     */
    public function alerts(Request $request): JsonResponse
    {
        $this->authorize('alerts', Retraite::class);

        $ageMin = (int) (RemoteConfig::getValue('retraite_age_minimum', '60') ?: 60);
        $ageLimite = (int) (RemoteConfig::getValue('retraite_age_limite', '65') ?: 65);
        $seuils = array_map('intval', array_filter(explode(',', (string) RemoteConfig::getValue('retraite_alerte_mois', '6,3,1'))));
        sort($seuils);

        $today = now()->startOfDay();
        $alerts = [];

        $agents = Agent::query()
            ->with('departement')
            ->whereNotNull('date_naissance')
            ->where('is_active', true)
            ->where('statut', '!=', 'Retraité')
            ->get();

        foreach ($agents as $agent) {
            $birth = Carbon::parse($agent->date_naissance)->startOfDay();
            $dateDroit = $birth->copy()->addYears($ageMin);
            $dateLimite = $birth->copy()->addYears($ageLimite);
            $age = $birth->diffInYears($today);

            $statutCalcule = 'Actif';
            if ($age >= $ageLimite) {
                $statutCalcule = 'Retraité';
            } elseif ($age >= $ageMin) {
                $statutCalcule = 'En cours de retraite';
            }

            $joursAvantDroit = $today->diffInDays($dateDroit, false);
            $alerteLabel = null;
            $priorite = 999;

            foreach ($seuils as $mois) {
                $joursSeuil = (int) round($mois * 30.44);
                if ($joursAvantDroit >= 0 && $joursAvantDroit <= $joursSeuil) {
                    $alerteLabel = "{$mois} mois";
                    $priorite = $mois;
                    break;
                }
            }

            if ($statutCalcule === 'En cours de retraite') {
                $alerteLabel = $alerteLabel ?? 'âge légal atteint';
                $priorite = 0;
            }

            if ($alerteLabel === null && $statutCalcule === 'Actif') {
                continue;
            }

            $alerts[] = [
                'agent_id' => $agent->id,
                'matricule' => $agent->matricule,
                'nom_complet' => $agent->nom_complet,
                'poste' => $agent->poste,
                'service' => $agent->departement?->nom,
                'photo_url' => MediaUrl::public($agent->photo_url),
                'age' => $age,
                'date_naissance' => $agent->date_naissance?->format('Y-m-d'),
                'date_droit_retraite' => $dateDroit->format('Y-m-d'),
                'date_limite_activite' => $dateLimite->format('Y-m-d'),
                'statut_calcule' => $statutCalcule,
                'alerte' => $alerteLabel,
                'priorite' => $priorite,
                'jours_avant_droit' => (int) $joursAvantDroit,
            ];
        }

        usort($alerts, fn ($a, $b) => $a['priorite'] <=> $b['priorite']);

        return response()->json([
            'config' => [
                'age_minimum' => $ageMin,
                'age_limite' => $ageLimite,
                'alerte_mois' => $seuils,
            ],
            'alerts' => $alerts,
            'count' => count($alerts),
        ]);
    }

    public function store(StoreRetraiteRequest $request): JsonResponse
    {
        $this->authorize('create', Retraite::class);

        $data = $request->validated();
        $data['statut'] = $data['statut'] ?? 'EN_COURS';
        $data['created_by'] = $request->user()->id;

        $retraite = Retraite::query()->create($data)->load(['agent.departement', 'creator']);

        if ($request->boolean('mark_agent_retraite')) {
            $retraite->agent?->update(['statut' => 'Retraité', 'is_active' => false]);
        }

        return response()->json([
            'message' => 'Dossier retraite créé.',
            'retraite' => new RetraiteResource($retraite),
        ], 201);
    }

    public function show(Retraite $retraite): JsonResponse
    {
        $this->authorize('view', $retraite);

        return response()->json([
            'retraite' => new RetraiteResource($retraite->load(['agent.departement', 'creator'])),
        ]);
    }

    public function update(UpdateRetraiteRequest $request, Retraite $retraite): JsonResponse
    {
        $this->authorize('update', $retraite);

        $retraite->fill($request->validated())->save();

        if ($retraite->statut === 'VALIDE' || $retraite->statut === 'TERMINE') {
            $retraite->agent?->update(['statut' => 'Retraité', 'is_active' => false]);
        }

        return response()->json([
            'message' => 'Dossier retraite mis à jour.',
            'retraite' => new RetraiteResource($retraite->fresh()->load(['agent.departement', 'creator'])),
        ]);
    }

    public function destroy(Retraite $retraite): JsonResponse
    {
        $this->authorize('delete', $retraite);

        $retraite->delete();

        return response()->json(['message' => 'Dossier retraite supprimé.']);
    }
}
