<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'notifications';

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_MOBILE = 'mobile';

    public const CHANNEL_BOTH = 'both';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'categorie',
        'is_read',
        'read_at',
        'related_model',
        'related_id',
        'play_sound',
        'channel',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'play_sound' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
