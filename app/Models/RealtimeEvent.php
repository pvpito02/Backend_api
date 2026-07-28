<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'audience',
        'audience_id',
        'type',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
