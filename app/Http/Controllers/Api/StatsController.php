<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use App\Services\WeeklyPointageReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatsController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
        private readonly WeeklyPointageReportService $weeklyReport,
    ) {}

    public function presence(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        return response()->json([
            'data' => $this->stats->presenceSeries(
                $request->input('from'),
                $request->input('to'),
            ),
        ]);
    }

    public function presenceByService(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        return response()->json([
            'data' => $this->stats->presenceByService($request->input('date')),
        ]);
    }

    public function retards(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        return response()->json(
            $this->stats->retardsStats($request->input('from'), $request->input('to'))
        );
    }

    public function demandes(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        return response()->json($this->stats->demandesStats());
    }

    public function reports(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        $period = $request->validate([
            'period' => ['sometimes', Rule::in(['daily', 'weekly', 'monthly', 'all'])],
        ])['period'] ?? 'all';

        return response()->json([
            'data' => $this->stats->reportsSummary($period === 'all' ? 'daily' : $period),
        ]);
    }

    /**
     * Rapport hebdomadaire imprimable (lundi → samedi) — modèle mairie.
     */
    public function weeklyPointage(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        $data = $request->validate([
            'date' => ['sometimes', 'nullable', 'date'],
        ]);

        $user = $request->user();

        return response()->json([
            'data' => $this->weeklyReport->generate(
                $data['date'] ?? null,
                $user?->name,
            ),
        ]);
    }
}
