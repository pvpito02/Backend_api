<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningShift extends Model
{
    protected $fillable = [
        'departement_id',
        'audience',
        'agent_id',
        'service_label',
        'shift_start',
        'shift_end',
        'manager_name',
        'required_count',
        'assigned_count',
        'statut',
        'date_effective',
        'work_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_effective' => 'date',
            'work_days' => 'array',
            'is_active' => 'boolean',
            'required_count' => 'integer',
            'assigned_count' => 'integer',
        ];
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function getShiftLabelAttribute(): string
    {
        $start = substr((string) $this->shift_start, 0, 5);
        $end = substr((string) $this->shift_end, 0, 5);

        return "{$start} - {$end}";
    }

    public static function queryForAgent(Agent $agent)
    {
        return static::query()->where('is_active', true)->where(function ($q) use ($agent) {
            $q->where(function ($b) use ($agent) {
                $b->where('audience', 'SERVICE')
                    ->where('departement_id', $agent->departement_id);
            })->orWhere(function ($b) use ($agent) {
                $b->where('audience', 'CONSEILLERS')
                    ->where('agent_id', $agent->id);
            });
        });
    }

    public static function hasAnyForAgent(Agent $agent): bool
    {
        return static::queryForAgent($agent)->exists();
    }

    public static function forAgentOnDate(Agent $agent, Carbon $at): ?self
    {
        $dated = static::queryForAgent($agent)
            ->whereDate('date_effective', $at->toDateString())
            ->orderByRaw("CASE statut WHEN 'CONFIRME' THEN 1 WHEN 'PROVISOIRE' THEN 2 ELSE 3 END")
            ->first();
        if ($dated) {
            return $dated;
        }

        $iso = (int) $at->dayOfWeekIso;
        $recurrent = static::queryForAgent($agent)->whereNull('date_effective')->get();
        foreach ($recurrent as $shift) {
            $days = $shift->work_days;
            if (! is_array($days) || $days === []) {
                $days = [1, 2, 3, 4, 5];
            }
            $days = array_map('intval', $days);
            if (in_array($iso, $days, true)) {
                return $shift;
            }
        }

        return null;
    }
}
