<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteConfig extends Model
{
    protected $fillable = [
        'key_name',
        'value_text',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()
            ->where('key_name', $key)
            ->where('is_active', true)
            ->first();

        return $row?->value_text ?? $default;
    }

    public static function mapActive(): array
    {
        return static::query()
            ->where('is_active', true)
            ->pluck('value_text', 'key_name')
            ->all();
    }
}
