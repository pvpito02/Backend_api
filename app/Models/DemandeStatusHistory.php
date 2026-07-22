<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'demande_status_history';

    protected $fillable = [
        'absence_request_id',
        'from_statut',
        'to_statut',
        'changed_by',
        'changed_by_label',
        'detail',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(AbsenceRequest::class, 'absence_request_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
