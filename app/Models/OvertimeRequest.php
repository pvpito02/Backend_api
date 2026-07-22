<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    protected $fillable = [
        'agent_id',
        'date_travail',
        'heures_sup',
        'motif',
        'statut',
        'approuve_par',
        'date_approbation',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'date_travail' => 'date',
            'heures_sup' => 'decimal:2',
            'date_approbation' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }
}
