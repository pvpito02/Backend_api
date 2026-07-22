<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pointage extends Model
{
    protected $fillable = [
        'agent_id',
        'site_id',
        'type',
        'date_pointage',
        'heure_pointage',
        'statut',
        'late_minutes',
        'source',
        'latitude',
        'longitude',
        'device_id',
        'is_visitor',
        'pending_sync',
        'note',
        'photo_path',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'date_pointage' => 'date',
            'late_minutes' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_visitor' => 'boolean',
            'pending_sync' => 'boolean',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(PointageAnomalie::class);
    }
}
