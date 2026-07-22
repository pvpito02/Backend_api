<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentDocument extends Model
{
    protected $fillable = [
        'agent_id',
        'type_document',
        'file_path',
        'original_name',
        'mime_type',
        'is_present',
        'uploaded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_present' => 'boolean',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
