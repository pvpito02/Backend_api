<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCode extends Model
{
    protected $table = 'qr_codes';

    protected $fillable = [
        'agent_id',
        'code',
        'issued_at',
        'expires_at',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function refreshExpiredStatus(): void
    {
        if ($this->statut === 'ACTIF' && $this->expires_at && $this->expires_at->isPast()) {
            $this->forceFill(['statut' => 'EXPIRE'])->save();
        }
    }
}
