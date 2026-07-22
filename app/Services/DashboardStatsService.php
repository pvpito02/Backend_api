<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\Agent;
use App\Models\AppNotification;
use App\Models\Mission;
use App\Models\Pointage;
use App\Models\PointageAnomalie;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    public function summary(?string $date = null): array
    {
        $day = Carbon::parse($date ?? now()->toDateString())->startOfDay();
        $yesterday = $day->copy()->subDay();

        $totalAgents = Agent::query()->where('is_active', true)->count();

        $presentsToday = $this->presentAgentIds($day)->count();
        $presentsYesterday = $this->presentAgentIds($yesterday)->count();

        $retardsToday = Pointage::query()
            ->whereDate('date_pointage', $day)
            ->where('type', 'ENTREE')
            ->where(function ($q) {
                $q->where('statut', 'RETARD')
                    ->orWhere('late_minutes', '>', 0);
            })
            ->distinct()
            ->count('agent_id');

        $retardsYesterday = Pointage::query()
            ->whereDate('date_pointage', $yesterday)
            ->where('type', 'ENTREE')
            ->where(function ($q) {
                $q->where('statut', 'RETARD')
                    ->orWhere('late_minutes', '>', 0);
            })
            ->distinct()
            ->count('agent_id');

        $pointagesToday = Pointage::query()->whereDate('date_pointage', $day)->count();
        $absents = max(0, $totalAgents - $presentsToday);
        $taux = $totalAgents > 0 ? round(($presentsToday / $totalAgents) * 100, 1) : 0.0;

        $demandesEnAttente = AbsenceRequest::query()
            ->where('statut', 'EN_ATTENTE')
            ->count();

        return [
            'date' => $day->toDateString(),
            'quick' => [
                'pointages_enregistres' => $pointagesToday,
                'agents_presents' => $presentsToday,
                'agents_absents' => $absents,
                'total_agents' => $totalAgents,
                'retards_detectes' => $retardsToday,
                'demandes_en_attente' => $demandesEnAttente,
            ],
            'kpis' => [
                'presents' => $presentsToday,
                'taux_presence' => $taux,
                'retards' => $retardsToday,
                'absences' => $absents,
                'delta_presents' => $presentsToday - $presentsYesterday,
                'delta_retards' => $retardsToday - $retardsYesterday,
            ],
        ];
    }

    public function alerts(int $limit = 8): array
    {
        $anomalies = PointageAnomalie::query()
            ->with(['pointage.agent'])
            ->where('resolved', false)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (PointageAnomalie $a) => [
                'title' => $a->type ?? 'Anomalie',
                'description' => $a->description
                    ?? trim(($a->pointage?->agent?->nom_complet ?? 'Agent').' — pointage #'.$a->pointage_id),
                'time' => optional($a->created_at)->toIso8601String(),
                'priority' => 'high',
                'link' => '/pointages',
            ]);

        $validations = AbsenceRequest::query()
            ->with('agent')
            ->where('statut', 'EN_ATTENTE')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AbsenceRequest $d) => [
                'title' => 'Demande '.$d->type_demande,
                'description' => ($d->agent?->nom_complet ?? 'Agent').' — '.$d->motif,
                'time' => optional($d->created_at)->toIso8601String(),
                'priority' => 'medium',
                'link' => '/demandes',
            ]);

        $missions = Mission::query()
            ->with('agent')
            ->whereIn('statut', ['PLANIFIEE', 'EN_COURS'])
            ->whereDate('date_debut', '<=', now())
            ->whereDate('date_fin', '>=', now())
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Mission $m) => [
                'title' => $m->titre,
                'description' => ($m->agent?->nom_complet ?? 'Agent').' — '.$m->lieu,
                'time' => optional($m->updated_at)->toIso8601String(),
                'priority' => 'low',
                'link' => '/missions',
            ]);

        $notifications = AppNotification::query()
            ->where('is_read', false)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AppNotification $n) => [
                'title' => $n->title,
                'description' => $n->message,
                'time' => optional($n->created_at)->toIso8601String(),
                'priority' => $n->play_sound ? 'high' : 'medium',
                'link' => '/notifications',
            ]);

        return [
            'groups' => [
                ['id' => 'anomalies', 'label' => 'Anomalies', 'items' => $anomalies],
                ['id' => 'validations', 'label' => 'Validations', 'items' => $validations],
                ['id' => 'missions', 'label' => 'Missions', 'items' => $missions],
                ['id' => 'notifications', 'label' => 'Notifications', 'items' => $notifications],
            ],
        ];
    }

    public function presenceSeries(?string $from = null, ?string $to = null): array
    {
        $end = Carbon::parse($to ?? now()->toDateString())->startOfDay();
        $start = Carbon::parse($from ?? $end->copy()->subDays(6)->toDateString())->startOfDay();
        $totalAgents = max(1, Agent::query()->where('is_active', true)->count());

        $series = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $presents = $this->presentAgentIds($day)->count();
            $series[] = [
                'date' => $day->toDateString(),
                'jour' => $day->locale('fr')->isoFormat('ddd'),
                'presents' => $presents,
                'taux' => round(($presents / $totalAgents) * 100, 1),
            ];
        }

        return $series;
    }

    public function presenceByService(?string $date = null): array
    {
        $day = Carbon::parse($date ?? now()->toDateString())->startOfDay();

        $presentIds = $this->presentAgentIds($day);

        $departements = DB::table('departements')
            ->leftJoin('agents', function ($join) {
                $join->on('agents.departement_id', '=', 'departements.id')
                    ->where('agents.is_active', true);
            })
            ->select(
                'departements.id as service_id',
                'departements.nom as service',
                DB::raw('COUNT(agents.id) as total'),
                DB::raw('SUM(CASE WHEN agents.id IN ('.$this->idsSql($presentIds).') THEN 1 ELSE 0 END) as presents')
            )
            ->groupBy('departements.id', 'departements.nom')
            ->orderBy('departements.nom')
            ->get();

        return $departements->map(function ($row) {
            $total = (int) $row->total;
            $presents = (int) $row->presents;

            return [
                'service_id' => (int) $row->service_id,
                'service' => $row->service,
                'total' => $total,
                'presents' => $presents,
                'taux' => $total > 0 ? round(($presents / $total) * 100, 1) : 0.0,
            ];
        })->all();
    }

    public function reportsSummary(string $period = 'daily'): array
    {
        $today = now()->startOfDay();

        $ranges = match ($period) {
            'weekly' => [
                ['id' => 'week', 'titre' => 'Rapport hebdomadaire', 'start' => $today->copy()->startOfWeek(), 'end' => $today],
            ],
            'monthly' => [
                ['id' => 'month', 'titre' => 'Rapport mensuel', 'start' => $today->copy()->startOfMonth(), 'end' => $today],
            ],
            default => [
                ['id' => 'today', 'titre' => 'Rapport quotidien', 'start' => $today, 'end' => $today],
                ['id' => 'week', 'titre' => 'Rapport hebdomadaire', 'start' => $today->copy()->startOfWeek(), 'end' => $today],
                ['id' => 'month', 'titre' => 'Rapport mensuel', 'start' => $today->copy()->startOfMonth(), 'end' => $today],
            ],
        };

        $totalAgents = max(1, Agent::query()->where('is_active', true)->count());

        return collect($ranges)->map(function (array $range) use ($totalAgents) {
            $start = $range['start'];
            $end = $range['end'];

            $presentsAvg = 0;
            $days = 0;
            foreach (CarbonPeriod::create($start, $end) as $day) {
                $presentsAvg += $this->presentAgentIds($day)->count();
                $days++;
            }
            $presents = $days > 0 ? (int) round($presentsAvg / $days) : 0;

            $retards = Pointage::query()
                ->whereBetween('date_pointage', [$start->toDateString(), $end->toDateString()])
                ->where('type', 'ENTREE')
                ->where(function ($q) {
                    $q->where('statut', 'RETARD')->orWhere('late_minutes', '>', 0);
                })
                ->distinct()
                ->count('agent_id');

            $absences = max(0, $totalAgents - $presents);

            return [
                'id' => $range['id'],
                'titre' => $range['titre'],
                'periode' => $start->toDateString().' → '.$end->toDateString(),
                'presents' => $presents,
                'retards' => $retards,
                'absences' => $absences,
                'taux' => round(($presents / $totalAgents) * 100, 1),
            ];
        })->values()->all();
    }

    public function retardsStats(?string $from = null, ?string $to = null): array
    {
        $toDay = Carbon::parse($to ?? now()->toDateString())->startOfDay();
        $fromDay = Carbon::parse($from ?? $toDay->copy()->startOfMonth()->toDateString())->startOfDay();
        $today = now()->toDateString();

        $todayCount = Pointage::query()
            ->whereDate('date_pointage', $today)
            ->where('type', 'ENTREE')
            ->where(function ($q) {
                $q->where('statut', 'RETARD')->orWhere('late_minutes', '>', 0);
            })
            ->count();

        $monthlyCount = Pointage::query()
            ->whereBetween('date_pointage', [$fromDay->toDateString(), $toDay->toDateString()])
            ->where('type', 'ENTREE')
            ->where(function ($q) {
                $q->where('statut', 'RETARD')->orWhere('late_minutes', '>', 0);
            })
            ->count();

        $entrees = Pointage::query()
            ->whereBetween('date_pointage', [$fromDay->toDateString(), $toDay->toDateString()])
            ->where('type', 'ENTREE')
            ->count();

        $trend = [];
        foreach (CarbonPeriod::create($fromDay, $toDay) as $day) {
            $trend[] = [
                'date' => $day->toDateString(),
                'retards' => Pointage::query()
                    ->whereDate('date_pointage', $day)
                    ->where('type', 'ENTREE')
                    ->where(function ($q) {
                        $q->where('statut', 'RETARD')->orWhere('late_minutes', '>', 0);
                    })
                    ->count(),
            ];
        }

        return [
            'today_count' => $todayCount,
            'monthly_count' => $monthlyCount,
            'late_rate_pct' => $entrees > 0 ? round(($monthlyCount / $entrees) * 100, 1) : 0.0,
            'trend' => $trend,
        ];
    }

    public function demandesStats(): array
    {
        $rows = AbsenceRequest::query()
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return [
            'en_attente' => (int) ($rows['EN_ATTENTE'] ?? 0),
            'en_cours' => (int) ($rows['EN_COURS'] ?? 0),
            'approuvees' => (int) ($rows['APPROUVEE'] ?? 0),
            'rejetees' => (int) ($rows['REJETEE'] ?? 0),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function presentAgentIds(Carbon $day)
    {
        return Pointage::query()
            ->whereDate('date_pointage', $day)
            ->where('type', 'ENTREE')
            ->distinct()
            ->pluck('agent_id');
    }

    /** @param  \Illuminate\Support\Collection<int, int>|\Illuminate\Support\Collection<int, mixed>  $ids */
    private function idsSql($ids): string
    {
        $list = $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($list->isEmpty()) {
            return '0';
        }

        return $list->implode(',');
    }
}
