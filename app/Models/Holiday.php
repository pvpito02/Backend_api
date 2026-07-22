<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'libelle',
        'date_holiday',
        'type_holiday',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_holiday' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
