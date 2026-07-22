<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileFeature extends Model
{
    protected $fillable = [
        'feature_key',
        'label',
        'description',
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
