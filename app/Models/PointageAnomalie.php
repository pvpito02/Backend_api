<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointageAnomalie extends Model
{
    protected $table = 'pointage_anomalies';

    protected $fillable = [
        'pointage_id',
        'type',
        'severite',
        'description',
        'resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function pointage(): BelongsTo
    {
        return $this->belongsTo(Pointage::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
