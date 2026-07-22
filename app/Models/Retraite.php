<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Retraite extends Model
{
    protected $fillable = [
        'agent_id',
        'date_depart',
        'motif',
        'statut',
        'montant_pension',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_depart' => 'date',
            'montant_pension' => 'decimal:2',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
