<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departement extends Model
{
    protected $fillable = [
        'code',
        'nom',
        'responsable_id',
        'email',
        'telephone',
        'description',
        'is_active',
        'work_days',
        'entry_time',
        'exit_time',
        'friday_exit_time',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'work_days' => 'array',
        ];
    }

    /**
     * Jours travaillés effectifs (1=lun … 7=dim).
     * Si work_days est null, retourne null → le caller utilise le global.
     */
    public function getEffectiveWorkDays(): ?array
    {
        return $this->work_days;
    }

    /**
     * Vérifie si un jour donné (Carbon dayOfWeekIso : 1=lun … 7=dim) est travaillé.
     * null = pas de config custom → caller doit vérifier le global.
     */
    public function isWorkDay(int $dayOfWeekIso): ?bool
    {
        if ($this->work_days === null) {
            return null;
        }

        return in_array($dayOfWeekIso, $this->work_days, true);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
