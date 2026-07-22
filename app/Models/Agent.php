<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agent extends Model
{
    protected $fillable = [
        'user_id',
        'matricule',
        'prenom',
        'nom',
        'sexe',
        'date_naissance',
        'date_entree',
        'date_fin_contrat',
        'poste',
        'departement_id',
        'supervisor_id',
        'email',
        'telephone',
        'photo_url',
        'statut',
        'is_active',
        'heure_travail_par_jour',
        'solde_conges',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_entree' => 'date',
            'date_fin_contrat' => 'date',
            'is_active' => 'boolean',
            'heure_travail_par_jour' => 'decimal:2',
            'solde_conges' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}
