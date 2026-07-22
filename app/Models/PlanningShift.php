<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningShift extends Model
{
    protected $fillable = [
        'departement_id',
        'service_label',
        'shift_start',
        'shift_end',
        'manager_name',
        'required_count',
        'assigned_count',
        'statut',
        'date_effective',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_effective' => 'date',
            'is_active' => 'boolean',
            'required_count' => 'integer',
            'assigned_count' => 'integer',
        ];
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function getShiftLabelAttribute(): string
    {
        $start = substr((string) $this->shift_start, 0, 5);
        $end = substr((string) $this->shift_end, 0, 5);

        return "{$start} - {$end}";
    }
}
