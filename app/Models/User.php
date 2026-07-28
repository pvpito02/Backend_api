<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone',
        'password',
        'avatar_url',
        'is_active',
        'last_login_at',
        'last_logout_at',
        'last_login_ip',
        'last_user_agent',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_logout_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $name = $this->role?->name;

        if ($name === null) {
            return false;
        }

        $roles = (array) $roles;

        return in_array($name, $roles, true);
    }

    public function isAdminStaff(): bool
    {
        return $this->hasRole(['super_admin', 'admin', 'sous_admin', 'rh', 'direction']);
    }

    /** Fenêtre d’activité (minutes) pour considérer une session « en ligne ». */
    public static function onlineThresholdMinutes(): int
    {
        return 5;
    }

    public static function onlineThreshold(): \Carbon\Carbon
    {
        return now()->subMinutes(static::onlineThresholdMinutes());
    }

    /**
     * Scope / contrainte SQL : tokens encore considérés actifs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Laravel\Sanctum\PersonalAccessToken>|\Illuminate\Database\Eloquent\Relations\MorphMany  $query
     */
    public static function constrainActiveTokens($query): void
    {
        $threshold = static::onlineThreshold();

        $query->where(function ($q) use ($threshold) {
            $q->where('last_used_at', '>=', $threshold)
                ->orWhere(function ($q2) use ($threshold) {
                    $q2->whereNull('last_used_at')
                        ->where('created_at', '>=', $threshold);
                });
        });
    }

    public function activeSessionsCount(): int
    {
        if (isset($this->active_sessions_count)) {
            return (int) $this->active_sessions_count;
        }

        $query = $this->tokens();
        static::constrainActiveTokens($query);

        return (int) $query->count();
    }

    public function isOnline(): bool
    {
        return $this->activeSessionsCount() > 0;
    }

    public function lastSeenAt(): ?\Carbon\Carbon
    {
        if (! empty($this->tokens_max_last_used_at)) {
            return \Carbon\Carbon::parse($this->tokens_max_last_used_at);
        }

        $lastUsed = $this->tokens()->max('last_used_at');
        if ($lastUsed) {
            return \Carbon\Carbon::parse($lastUsed);
        }

        $lastCreated = $this->tokens()->max('created_at');

        return $lastCreated ? \Carbon\Carbon::parse($lastCreated) : $this->last_login_at;
    }
}
