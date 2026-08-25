<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /**
     * Rôles seedés / critiques — non supprimables, slug non modifiable.
     *
     * @var list<string>
     */
    public const SYSTEM_NAMES = [
        'super_admin',
        'admin',
        'sous_admin',
        'conseiller',
        'agent',
        'rh',
        'direction',
    ];

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isSystem(): bool
    {
        return in_array($this->name, self::SYSTEM_NAMES, true);
    }
}
