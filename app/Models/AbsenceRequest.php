<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenceRequest extends Model
{
    protected $table = 'absence_requests';

    protected $fillable = [
        'agent_id',
        'type_demande',
        'date_debut',
        'date_fin',
        'heure_debut',
        'heure_fin',
        'motif',
        'extra_json',
        'document_path',
        'statut',
        'lue_par_admin_at',
        'lue_par_admin_id',
        'approuve_par',
        'date_approbation',
        'motif_rejet',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'extra_json' => 'array',
            'lue_par_admin_at' => 'datetime',
            'date_approbation' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function lecteurAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lue_par_admin_id');
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }

    public function history(): HasMany
    {
        return $this->hasMany(DemandeStatusHistory::class, 'absence_request_id')->orderBy('created_at');
    }
}
