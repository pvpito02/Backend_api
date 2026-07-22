<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    protected $fillable = [
        'name',
        'entry_time',
        'exit_time',
        'friday_exit_time',
        'late_tolerance_minutes',
        'work_saturday',
        'block_sunday',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'work_saturday' => 'boolean',
            'block_sunday' => 'boolean',
            'is_active' => 'boolean',
            'late_tolerance_minutes' => 'integer',
        ];
    }

    public static function activeDefault(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('name', 'default')
            ->first()
            ?? static::query()->where('is_active', true)->first();
    }
}
