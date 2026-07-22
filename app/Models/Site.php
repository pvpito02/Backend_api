<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = [
        'code',
        'name',
        'latitude',
        'longitude',
        'radius_meters',
        'qr_payload',
        'maps_url',
        'services_rule',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function departements(): BelongsToMany
    {
        return $this->belongsToMany(Departement::class, 'site_departement');
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class);
    }
}
